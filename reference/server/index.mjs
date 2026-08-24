import {
  randomBytes,
  randomUUID,
  scrypt as scryptCallback,
  timingSafeEqual,
} from "node:crypto";
import { mkdtemp, readFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { promisify } from "node:util";

import { App } from "@tinyhttp/app";
import { cors } from "@tinyhttp/cors";
import { createApp } from "json-server/lib/app.js";
import { countries, getEmojiFlag } from "countries-list";
import { Low } from "lowdb";
import { JSONFile } from "lowdb/node";
import { json } from "milliparsec";
import { importCountry } from "../scripts/import-country.mjs";
import {
  countryFeatureCollections,
  ensureCatalog,
  rankEntities,
  rankSightsWithCityCoverage,
} from "./lib/catalog.mjs";
import {
  commonsImageSearch,
  geonamesCities,
  restCountry,
  wikipediaSearch,
} from "./providers.mjs";

const port = Number(process.env.PORT ?? 3001);
const host = process.env.HOST ?? "0.0.0.0";
const file = resolve(process.cwd(), process.env.DB_FILE ?? "server/db.json");
const db = new Low(new JSONFile(file), {
  visits: [],
  profile: {},
  users: [],
  completions: [],
  wishlists: [],
  rewards: [],
  collectionProgress: [],
  friends: [],
  managedCollections: [],
  dailyDestinations: [],
});
await db.read();
db.data.users ??= [];
db.data.completions ??= [];
db.data.wishlists ??= [];
db.data.rewards ??= [];
db.data.collectionProgress ??= [];
db.data.friends ??= [];
db.data.managedCollections ??= [];
db.data.dailyDestinations ??= [];
ensureCatalog(db);
const scrypt = promisify(scryptCallback);
const automaticImports = new Map();
const automaticImportFailures = new Map();
const transientCatalogs = new Map();
const resolvedCityImages = new Map();
const resolvedPlaceImages = new Map();

function countryShell(code, country, cities) {
  const countryId = `live-country-${code}`;
  const collections = countryFeatureCollections(country).map((item) => ({
    id: `collection-${item.slug}`,
    ...item,
  }));
  return {
    countries: [
      {
        id: countryId,
        ...country,
        description: `${country.name} is a country in ${country.region || country.continent}.`,
        coverImageUrl: "",
      },
    ],
    cities: cities.map((city, index) => ({
      id: `city-${code}-${city.geonamesId}`,
      countryId,
      ...city,
      slug: String(city.name)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-"),
      description: "",
      imageUrl: "",
      isFeatured: true,
      displayOrder: index,
    })),
    sights: [],
    collections,
    countryCollections: collections.map((collection, displayOrder) => ({
      countryId,
      collectionId: collection.id,
      displayOrder,
    })),
    imageCredits: [],
  };
}

async function enrichShellCityImages(country, catalog) {
  let cursor = 0;
  async function worker() {
    while (cursor < catalog.cities.length) {
      const city = catalog.cities[cursor++];
      const matches = await wikipediaSearch(`${city.name} ${country.name}`, {
        timeoutMs: 8_000,
        retries: 1,
      }).catch(() => []);
      const nearby = matches
        .filter(
          (item) =>
            item.imageUrl &&
            Number.isFinite(item.latitude) &&
            Number.isFinite(item.longitude),
        )
        .map((item) => ({
          ...item,
          distance: Math.hypot(
            item.latitude - city.latitude,
            item.longitude - city.longitude,
          ),
        }))
        .filter((item) => item.distance < 0.5)
        .sort((a, b) => a.distance - b.distance);
      let image = nearby[0];
      if (!image) {
        image = await commonsImageSearch(
          `${city.name} ${country.name} city skyline photograph`,
          { timeoutMs: 8_000, retries: 1 },
        ).catch(() => ({}));
      }
      if (image?.imageUrl) {
        city.imageUrl = image.imageUrl;
        city.wikipediaTitle = image.wikipediaTitle ?? city.wikipediaTitle;
        city.wikidataId = image.wikidataId ?? city.wikidataId;
      }
    }
  }
  await Promise.all(Array.from({ length: 4 }, () => worker()));
}

async function addShellSights(code, country, catalog) {
  const groups = await Promise.all(
    catalog.cities.map(async (city) => {
      const matches = await wikipediaSearch(
        `tourist attractions landmarks ${city.name} ${country.name}`,
        { timeoutMs: 5_000, retries: 0 },
      ).catch(() => []);
      return matches
        .filter(
          (item) =>
            !/^(list of|tourism in)/i.test(item.name) &&
            item.name.toLowerCase() !== country.name.toLowerCase(),
        )
        .slice(0, 3)
        .map((item) => ({
          ...item,
          id: `sight-${item.wikidataId ?? `${code}-${item.wikipediaTitle}`}`,
          countryId: catalog.countries[0].id,
          cityId: city.id,
          category: "attraction",
          latitude: Number.isFinite(item.latitude)
            ? item.latitude
            : city.latitude,
          longitude: Number.isFinite(item.longitude)
            ? item.longitude
            : city.longitude,
          imageUrl: item.imageUrl ?? "",
          isFeatured: true,
        }));
    }),
  );
  const candidates = groups
    .flat()
    .filter(
      (item, index, all) =>
        all.findIndex((candidate) => candidate.id === item.id) === index,
    );
  catalog.sights = rankSightsWithCityCoverage(
    candidates,
    catalog.cities.map((city) => city.id),
    20,
  ).map((item, index) => ({
    ...item,
    displayOrder: index,
    isPremium: index >= 3,
  }));
  const collections = countryFeatureCollections(country, catalog.sights).map(
    (item) => ({ id: `collection-${item.slug}`, ...item }),
  );
  catalog.collections = collections;
  catalog.countryCollections = collections.map((collection, displayOrder) => ({
    countryId: catalog.countries[0].id,
    collectionId: collection.id,
    displayOrder,
  }));
}

function startAutomaticImport(code) {
  const running = automaticImports.get(code);
  if (running) return running;

  const task = Promise.all([restCountry(code), geonamesCities(code)])
    .then(([country, cities]) => {
      const catalog = countryShell(code, country, cities);
      transientCatalogs.set(code, catalog);
      void addShellSights(code, country, catalog);
      void enrichShellCityImages(country, catalog);
    })
    .then(() => mkdtemp(join(tmpdir(), "stampo-country-")))
    .then(async (directory) => {
      const temporaryFile = join(directory, "catalog.json");
      try {
        const summary = await importCountry(code, {
          dbFile: temporaryFile,
          onProgress: (catalog) => transientCatalogs.set(code, catalog),
        });
        const catalog = JSON.parse(await readFile(temporaryFile, "utf8"));
        transientCatalogs.set(code, catalog);
        return summary;
      } finally {
        await rm(directory, { recursive: true, force: true });
      }
    })
    .then((summary) => {
      automaticImportFailures.delete(code);
      return summary;
    })
    .catch((error) => {
      automaticImportFailures.set(code, {
        message:
          error instanceof Error ? error.message : "Country import failed.",
        failedAt: Date.now(),
      });
      return null;
    })
    .finally(() => automaticImports.delete(code));

  automaticImports.set(code, task);
  return task;
}

async function hashPassword(password) {
  const salt = randomBytes(16).toString("hex");
  const hash = await scrypt(password, salt, 64);
  return `${salt}:${Buffer.from(hash).toString("hex")}`;
}

async function passwordMatches(password, stored) {
  if (!stored?.includes(":")) return password === stored; // migrate development accounts on next sign-in
  const [salt, expectedHex] = stored.split(":");
  const actual = Buffer.from(await scrypt(password, salt, 64));
  const expected = Buffer.from(expectedHex, "hex");
  return actual.length === expected.length && timingSafeEqual(actual, expected);
}

const sessions = new Map();
const app = new App();
app.use(cors());
app.options("*", cors());
app.use(json());

const adminApiKey = process.env.ADMIN_API_KEY ?? "stampo-local-admin";

function requireAdmin(req, res) {
  const supplied = bearerToken(req) ?? String(req.headers["x-admin-key"] ?? "");
  const actual = Buffer.from(supplied);
  const expected = Buffer.from(adminApiKey);
  const valid =
    actual.length === expected.length && timingSafeEqual(actual, expected);
  if (!valid) res.status(401).json({ message: "Invalid admin API key." });
  return valid;
}

function requiredText(value, field, res) {
  const result = String(value ?? "").trim();
  if (!result) res.status(422).json({ message: `${field} is required.` });
  return result;
}

function publicUser(user) {
  return {
    id: user.id,
    name: user.name,
    email: user.email,
    language: user.language ?? "English",
    plan: user.plan ?? "free",
  };
}

function bearerToken(req) {
  const authorization = req.headers.authorization ?? "";
  return authorization.startsWith("Bearer ") ? authorization.slice(7) : null;
}

function authenticatedUser(req) {
  const token = bearerToken(req);
  const userId = token ? sessions.get(token) : null;
  return db.data.users.find((user) => String(user.id) === String(userId));
}

function requireUser(req, res) {
  const user = authenticatedUser(req);
  if (!user) res.status(401).json({ message: "Unauthenticated." });
  return user;
}

function friendCodeFor(user) {
  user.friendCode ??= randomBytes(18).toString("base64url");
  return `stampo://friend/${user.friendCode}`;
}

function communityProfile(user) {
  const dashboard = homeDashboardFor(user);
  return {
    id: user.id,
    name: user.name,
    photoUri: user.photoUri ?? null,
    score: dashboard.score,
    level: dashboard.level,
    stats: {
      countries: dashboard.counts.countries,
      continents: dashboard.counts.continents,
      cities: dashboard.counts.cities,
      collections: collectionsFor(user.id).filter(
        (collection) => collection.status === "completed",
      ).length,
    },
  };
}

const HOME_CONTINENT_TOTALS = {
  AF: 54,
  AN: 0,
  AS: 48,
  EU: 44,
  NA: 23,
  OC: 14,
  SA: 12,
};
const HOME_SCORE_MAXIMUMS = {
  continents: 7,
  countries: 48.75,
  cities: 10,
  airports: 8,
  sights: 20,
  challenges: 6.25,
};
const COLLECTIONS = [
  {
    id: "wonders",
    title: "Seven Wonders",
    detail: "Visit all 7 wonders",
    defaultProgress: 12,
  },
  {
    id: "seas",
    title: "Seven Seas",
    detail: "Sail or visit all 7 seas",
    defaultProgress: 8,
  },
  {
    id: "unesco",
    title: "UNESCO Explorer",
    detail: "Visit heritage sites",
    defaultProgress: 14,
  },
  {
    id: "parks",
    title: "National Parks",
    detail: "Visit national parks",
    defaultProgress: 16,
  },
  {
    id: "usa",
    title: "United States Explorer",
    detail: "Visit all 50 states",
    defaultProgress: 0,
  },
];
const DEFAULT_COLLECTION_PLACES = {
  wonders: [
    ["great-wall", "Great Wall of China", "Beijing", "China"],
    ["petra", "Petra", "Ma'an", "Jordan"],
    ["colosseum", "Colosseum", "Rome", "Italy"],
    ["chichen-itza", "Chichén Itzá", "Yucatán", "Mexico"],
    ["machu-picchu", "Machu Picchu", "Cusco", "Peru"],
    ["taj-mahal", "Taj Mahal", "Agra", "India"],
    ["christ-redeemer", "Christ the Redeemer", "Rio de Janeiro", "Brazil"],
  ],
  seas: [
    ["arctic-ocean", "Arctic Ocean", "Tromsø", "Norway"],
    ["north-atlantic", "North Atlantic Ocean", "Reykjavík", "Iceland"],
    ["south-atlantic", "South Atlantic Ocean", "Cape Town", "South Africa"],
    ["north-pacific", "North Pacific Ocean", "Honolulu", "United States"],
    ["south-pacific", "South Pacific Ocean", "Suva", "Fiji"],
    ["indian-ocean", "Indian Ocean", "Malé", "Maldives"],
    ["southern-ocean", "Southern Ocean", "Ushuaia", "Argentina"],
  ],
  unesco: [
    ["angkor-wat", "Angkor Wat", "Siem Reap", "Cambodia"],
    ["acropolis", "Acropolis of Athens", "Athens", "Greece"],
    ["alhambra", "Alhambra", "Granada", "Spain"],
    ["mont-saint-michel", "Mont-Saint-Michel", "Normandy", "France"],
    ["moai", "Rapa Nui National Park", "Easter Island", "Chile"],
    ["serengeti", "Serengeti National Park", "Mara", "Tanzania"],
  ],
  parks: [
    ["yellowstone", "Yellowstone National Park", "Wyoming", "United States"],
    ["banff", "Banff National Park", "Alberta", "Canada"],
    ["fiordland", "Fiordland National Park", "Te Anau", "New Zealand"],
    ["torres-del-paine", "Torres del Paine", "Patagonia", "Chile"],
    ["kruger", "Kruger National Park", "Mpumalanga", "South Africa"],
    ["fuji-hakone", "Fuji-Hakone-Izu National Park", "Hakone", "Japan"],
  ],
  usa: [
    ["statue-liberty", "Statue of Liberty", "New York City", "United States"],
    ["grand-canyon", "Grand Canyon", "Arizona", "United States"],
    ["golden-gate", "Golden Gate Bridge", "San Francisco", "United States"],
    ["national-mall", "National Mall", "Washington, D.C.", "United States"],
    ["french-quarter", "French Quarter", "New Orleans", "United States"],
    ["waikiki", "Waikīkī Beach", "Honolulu", "United States"],
  ],
};
function defaultPlacesFor(collectionId) {
  return (DEFAULT_COLLECTION_PLACES[collectionId] ?? []).map(
    ([id, name, city, country]) => ({
      id,
      name,
      city,
      country,
      imageUrl: "",
      content: "",
      isPremium: false,
    }),
  );
}
if (db.data.managedCollections.length === 0) {
  db.data.managedCollections = COLLECTIONS.map((collection, displayOrder) => ({
    ...collection,
    imageUrl: "",
    description: collection.detail,
    isPublished: true,
    isPremium: false,
    displayOrder,
    places: defaultPlacesFor(collection.id),
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  }));
}
for (const collection of db.data.managedCollections) {
  if ((collection.places ?? []).length === 0) {
    collection.places = defaultPlacesFor(collection.id);
    collection.updatedAt = new Date().toISOString();
  }
}
if (db.data.dailyDestinations.length === 0) {
  db.data.dailyDestinations = [
    {
      id: "daily-france-louvre",
      country: "France",
      city: "Paris",
      name: "The Louvre",
      content:
        "The Louvre in Paris is the most visited art museum on Earth, drawing millions of visitors every year.",
      imageUrl: "",
      icon: "🖼️",
      question:
        "Which museum in Paris is the most visited art museum in the world?",
      options: ["The Louvre", "The Uffizi", "The British Museum", "The Prado"],
      correctAnswer: 0,
      publishDate: "",
      isPublished: true,
      isPremium: false,
      displayOrder: 0,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    },
  ];
}
await db.write();
const COUNTRY_CATALOG = {
  FR: {
    name: "France",
    flag: "🇫🇷",
    heroCities: [
      { id: "paris", name: "Paris", imageKey: "paris-eiffel" },
      { id: "lyon", name: "Lyon", imageKey: "lyon" },
      { id: "marseille", name: "Marseille", imageKey: "marseille" },
      { id: "nice", name: "Nice", imageKey: "nice" },
      { id: "paris", name: "Paris", imageKey: "paris-notre-dame" },
    ],
    featuredIn: ["🏛️ Cultural Icons", "🥐 Food Capitals", "✨ European Gems"],
    sights: [
      {
        id: "eiffel",
        name: "Eiffel Tower",
        imageKey: "eiffel",
        cityId: "paris",
        cityName: "Paris",
      },
      {
        id: "louvre",
        name: "Louvre Museum",
        imageKey: "louvre",
        cityId: "paris",
        cityName: "Paris",
      },
      {
        id: "arc",
        name: "Arc de Triomphe",
        imageKey: "arc",
        cityId: "paris",
        cityName: "Paris",
      },
      {
        id: "versailles",
        name: "Palace of Versailles",
        imageKey: "versailles",
        cityId: "versailles",
        cityName: "Versailles",
      },
      {
        id: "mont-saint-michel",
        name: "Mont-Saint-Michel",
        imageKey: "mont-saint-michel",
        cityId: "mont-saint-michel",
        cityName: "Mont-Saint-Michel",
      },
      {
        id: "pont-du-gard",
        name: "Pont du Gard",
        imageKey: "pont-du-gard",
        cityId: "nimes",
        cityName: "Nîmes",
        premium: true,
      },
      {
        id: "villefranche",
        name: "Villefranche-sur-Mer",
        imageKey: "villefranche",
        cityId: "villefranche-sur-mer",
        cityName: "Villefranche-sur-Mer",
        premium: true,
      },
    ],
  },
};

function collectionsFor(userId) {
  const completed = new Set(
    db.data.completions
      .filter((item) => String(item.userId) === String(userId))
      .map((item) => String(item.sightId)),
  );
  return db.data.managedCollections
    .filter((collection) => collection.isPublished !== false)
    .sort((a, b) => Number(a.displayOrder ?? 0) - Number(b.displayOrder ?? 0))
    .map((collection) => {
      const saved = db.data.collectionProgress.find(
        (item) =>
          String(item.userId) === String(userId) &&
          item.collectionId === collection.id,
      );
      const places = collection.places ?? [];
      const completedCount = places.filter((place) =>
        completed.has(`collection-${collection.id}-${place.id}`),
      ).length;
      const derivedProgress = places.length
        ? Math.round((completedCount / places.length) * 100)
        : Number(collection.defaultProgress ?? 0);
      const progress = Math.min(
        100,
        Math.max(0, Number(saved?.progress ?? derivedProgress)),
      );
      return {
        id: collection.id,
        title: collection.title,
        detail: collection.detail,
        description: collection.description ?? collection.detail,
        imageUrl: collection.imageUrl ?? "",
        isPremium: collection.isPremium === true,
        places,
        progress,
        status:
          progress >= 100 ? "completed" : progress > 0 ? "active" : "inactive",
        updatedAt: saved?.updatedAt,
      };
    });
}

function cappedPoints(count, each, maximum) {
  return Math.min(Math.max(0, count) * each, maximum);
}

function challengePointsFor(userId) {
  return Math.min(
    HOME_SCORE_MAXIMUMS.challenges,
    db.data.rewards
      .filter(
        (reward) =>
          String(reward.userId) === String(userId) && reward.unlocked !== false,
      )
      .reduce(
        (total, reward) =>
          total + Number(reward.krooPoints ?? reward.points ?? 0),
        0,
      ),
  );
}

function levelFor(score) {
  if (score >= 75) return "Kroo Master";
  if (score >= 50) return "Voyager";
  if (score >= 30) return "Wayfarer";
  if (score >= 15) return "Explorer";
  if (score >= 5) return "Traveler";
  return "Wanderer";
}

function homeDashboardFor(user) {
  const visits = db.data.visits.filter(
    (visit) => String(visit.userId) === String(user.id),
  );
  const countries = new Set(
    visits.map((visit) => visit.countryCode).filter(Boolean),
  );
  const continents = new Set(
    visits.map((visit) => visit.continentCode).filter(Boolean),
  );
  const cities = new Set(visits.map((visit) => visit.cityId).filter(Boolean));
  const airports = visits.reduce(
    (total, visit) =>
      total +
      (visit.places ?? []).filter((place) => place.type === "airport").length,
    0,
  );
  const recordedSightIds = visits.flatMap((visit) =>
    (visit.places ?? [])
      .filter((place) => place.type === "sight")
      .map((place) => String(place.id)),
  );
  const completedSightIds = db.data.completions
    .filter((completion) => String(completion.userId) === String(user.id))
    .map((completion) => String(completion.sightId));
  const sights = new Set([...recordedSightIds, ...completedSightIds]).size;
  const challengePoints = challengePointsFor(user.id);
  const rawScore =
    cappedPoints(continents.size, 1, HOME_SCORE_MAXIMUMS.continents) +
    cappedPoints(countries.size, 0.25, HOME_SCORE_MAXIMUMS.countries) +
    cappedPoints(cities.size, 0.005, HOME_SCORE_MAXIMUMS.cities) +
    cappedPoints(airports, 0.01, HOME_SCORE_MAXIMUMS.airports) +
    cappedPoints(sights, 0.002, HOME_SCORE_MAXIMUMS.sights) +
    challengePoints;
  const score = Math.round(Math.min(100, rawScore) * 1000) / 1000;
  const continentCountries = {};
  visits.forEach((visit) => {
    if (!visit.continentCode || !visit.countryCode) return;
    continentCountries[visit.continentCode] ??= new Set();
    continentCountries[visit.continentCode].add(visit.countryCode);
  });

  return {
    counts: {
      continents: continents.size,
      countries: countries.size,
      cities: cities.size,
      airports,
      sights,
    },
    score,
    level: levelFor(score),
    challengePoints,
    worldProgress: Math.round((countries.size / 195) * 100),
    visitedCountryCodes: [...countries].sort(),
    continentCounts: Object.fromEntries(
      Object.keys(HOME_CONTINENT_TOTALS).map((code) => [
        code,
        continentCountries[code]?.size ?? 0,
      ]),
    ),
    updatedAt: new Date().toISOString(),
  };
}

app.post("/auth/register", async (req, res) => {
  const { name, email, password, passwordConfirmation } = req.body ?? {};
  if (!name || !email || !password || password.length < 6) {
    return res.status(422).json({
      message: "Name, email, and a 6-character password are required.",
    });
  }
  if (password !== passwordConfirmation) {
    return res
      .status(422)
      .json({ message: "Password confirmation does not match." });
  }
  const normalizedEmail = String(email).trim().toLocaleLowerCase();
  if (
    db.data.users.some(
      (user) => user.email.toLocaleLowerCase() === normalizedEmail,
    )
  ) {
    return res
      .status(422)
      .json({ message: "An account with this email already exists." });
  }

  const user = {
    id: randomUUID(),
    name: String(name).trim(),
    email: normalizedEmail,
    password: await hashPassword(password),
    language: "English",
    plan: "free",
  };
  db.data.users.push(user);
  await db.write();
  const token = `dev-${randomUUID()}`;
  sessions.set(token, user.id);
  return res.status(201).json({ token, user: publicUser(user) });
});

app.post("/auth/login", async (req, res) => {
  const { email, password } = req.body ?? {};
  const normalizedEmail = String(email ?? "")
    .trim()
    .toLocaleLowerCase();
  const user = db.data.users.find(
    (candidate) => candidate.email.toLocaleLowerCase() === normalizedEmail,
  );
  if (user && !(await passwordMatches(password, user.password)))
    return res.status(422).json({ message: "Invalid email or password." });
  if (!user)
    return res.status(422).json({ message: "Invalid email or password." });
  const token = `dev-${randomUUID()}`;
  sessions.set(token, user.id);
  return res.json({ token, user: publicUser(user) });
});

app.get("/auth/me", (req, res) => {
  const user = authenticatedUser(req);
  return user
    ? res.json(publicUser(user))
    : res.status(401).json({ message: "Unauthenticated." });
});

app.put("/auth/password", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const { currentPassword, newPassword } = req.body ?? {};
  if (!(await passwordMatches(String(currentPassword ?? ""), user.password))) {
    return res
      .status(422)
      .json({ message: "The current password is incorrect." });
  }
  if (String(newPassword ?? "").length < 8) {
    return res.status(422).json({
      message: "The new password must contain at least 8 characters.",
    });
  }
  user.password = await hashPassword(String(newPassword));
  await db.write();
  return res.status(204).send();
});

app.post("/auth/logout", (req, res) => {
  const token = bearerToken(req);
  if (token) sessions.delete(token);
  return res.status(204).send();
});

app.get("/profile", (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  return res.json({
    ...publicUser(user),
    nationality: user.nationality ?? "",
    dateOfBirth: user.dateOfBirth ?? "",
    photoUri: user.photoUri ?? null,
  });
});

app.put("/profile", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const allowed = [
    "name",
    "email",
    "language",
    "nationality",
    "dateOfBirth",
    "photoUri",
  ];
  allowed.forEach((key) => {
    if (req.body?.[key] !== undefined) user[key] = req.body[key];
  });
  await db.write();
  return res.json({
    ...publicUser(user),
    nationality: user.nationality ?? "",
    dateOfBirth: user.dateOfBirth ?? "",
    photoUri: user.photoUri ?? null,
  });
});

app.get("/visits", (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  return res.json(
    db.data.visits.filter((visit) => String(visit.userId) === String(user.id)),
  );
});

app.post("/visits", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const visit = {
    ...req.body,
    id: randomUUID(),
    userId: user.id,
    places: req.body?.places ?? [],
  };
  db.data.visits.push(visit);
  await db.write();
  return res.status(201).json(visit);
});

app.put("/visits/:id", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const index = db.data.visits.findIndex(
    (visit) =>
      String(visit.id) === String(req.params.id) &&
      String(visit.userId) === String(user.id),
  );
  if (index < 0) return res.status(404).json({ message: "Visit not found." });
  const visit = { ...req.body, id: db.data.visits[index].id, userId: user.id };
  db.data.visits[index] = visit;
  await db.write();
  return res.json(visit);
});

app.delete("/visits/:id", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const index = db.data.visits.findIndex(
    (visit) =>
      String(visit.id) === String(req.params.id) &&
      String(visit.userId) === String(user.id),
  );
  if (index < 0) return res.status(404).json({ message: "Visit not found." });
  db.data.visits.splice(index, 1);
  await db.write();
  return res.status(204).send();
});

app.get("/me/travel-state", (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  return res.json({
    completedSightIds: db.data.completions
      .filter((x) => x.userId === user.id)
      .map((x) => x.sightId),
    wishlistIds: db.data.wishlists
      .filter((x) => x.userId === user.id)
      .map((x) => x.targetId),
    rewards: db.data.rewards.filter((x) => x.userId === user.id),
    challengePoints: challengePointsFor(user.id),
    collections: collectionsFor(user.id),
    plan: user.plan ?? "free",
  });
});

app.get("/me/home", (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  return res.json(homeDashboardFor(user));
});

app.get("/community/leaderboard", (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const scope = req.query?.scope === "friends" ? "friends" : "global";
  const friendIds = new Set(
    db.data.friends
      .filter(
        (friendship) =>
          friendship.userId === user.id || friendship.friendId === user.id,
      )
      .map((friendship) =>
        friendship.userId === user.id
          ? friendship.friendId
          : friendship.userId,
      ),
  );
  const visibleUsers =
    scope === "friends"
      ? db.data.users.filter(
          (candidate) => candidate.id === user.id || friendIds.has(candidate.id),
        )
      : db.data.users;
  return res.json(
    visibleUsers
      .map(communityProfile)
      .sort((left, right) => right.score - left.score || left.name.localeCompare(right.name))
      .slice(0, 10),
  );
});

app.get("/me/friend-code", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const code = friendCodeFor(user);
  await db.write();
  return res.json({ code });
});

app.post("/me/friends/scan", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const scannedCode = String(req.body?.code ?? "").trim();
  const token = scannedCode.match(/^stampo:\/\/friend\/([^/?#]+)$/)?.[1];
  const friend = token
    ? db.data.users.find((candidate) => candidate.friendCode === token)
    : null;
  if (!friend)
    return res.status(422).json({ message: "This is not a valid Stampo friend code." });
  if (friend.id === user.id)
    return res.status(422).json({ message: "You cannot add your own friend code." });
  const exists = db.data.friends.some(
    (item) =>
      (item.userId === user.id && item.friendId === friend.id) ||
      (item.userId === friend.id && item.friendId === user.id),
  );
  if (!exists) {
    db.data.friends.push({
      id: randomUUID(),
      userId: user.id,
      friendId: friend.id,
      createdAt: new Date().toISOString(),
    });
    await db.write();
  }
  return res.json(communityProfile(friend));
});

function catalogForCountry(code) {
  return db.data.countries.some((item) => item.iso2 === code)
    ? db.data
    : transientCatalogs.get(code);
}

function availableCatalogs() {
  return [db.data, ...transientCatalogs.values()];
}

function creditFor(entityType, entityId, catalog = db.data) {
  return (
    catalog.imageCredits.find(
      (item) => item.entityType === entityType && item.entityId === entityId,
    ) ?? null
  );
}

function publicCity(city, catalog = db.data) {
  return {
    id: city.id,
    countryId: city.countryId,
    geonamesId: city.geonamesId ?? null,
    wikidataId: city.wikidataId ?? null,
    wikipediaTitle: city.wikipediaTitle ?? null,
    name: city.name,
    slug: city.slug,
    description: city.description ?? "",
    population: Number(city.population ?? 0),
    latitude: Number(city.latitude),
    longitude: Number(city.longitude),
    image: city.imageUrl ?? "",
    imageCredit: creditFor("city", city.id, catalog),
  };
}

function publicSight(sight, catalog = db.data) {
  const city = catalog.cities.find((item) => item.id === sight.cityId);
  return {
    id: sight.id,
    countryId: sight.countryId,
    cityId: sight.cityId,
    city: city?.name ?? "",
    opentripmapXid: sight.opentripmapXid ?? null,
    wikidataId: sight.wikidataId ?? null,
    wikipediaTitle: sight.wikipediaTitle ?? null,
    name: sight.name,
    slug: sight.slug,
    description: sight.description ?? "",
    category: sight.category ?? "attraction",
    latitude: Number(sight.latitude),
    longitude: Number(sight.longitude),
    image: sight.imageUrl ?? "",
    imageCredit: creditFor("sight", sight.id, catalog),
    isPremium: sight.isPremium === true,
  };
}

function catalogCountryPayload(code, req) {
  const catalog = catalogForCountry(code);
  const country = catalog?.countries.find((item) => item.iso2 === code);
  if (!country) return null;
  if (
    catalog === transientCatalogs.get(code) &&
    automaticImports.has(code) &&
    catalog.sights.length === 0
  )
    return null;
  const cities = rankEntities(
    catalog.cities.filter(
      (item) => item.countryId === country.id && item.isFeatured !== false,
    ),
    10,
  );
  const sights = rankEntities(
    catalog.sights.filter(
      (item) => item.countryId === country.id && item.isFeatured !== false,
    ),
    20,
  );
  const featuredIn = catalog.countryCollections
    .filter((item) => item.countryId === country.id)
    .sort((a, b) => a.displayOrder - b.displayOrder)
    .map((join) =>
      catalog.collections.find((item) => item.id === join.collectionId),
    )
    .filter(Boolean)
    .map(({ name, icon, slug }) => ({ name, icon, slug }));
  const user = authenticatedUser(req);
  const visits = user
    ? db.data.visits.filter(
        (visit) =>
          String(visit.userId) === String(user.id) &&
          visit.countryCode === code,
      )
    : [];
  const completed = new Set(
    user
      ? db.data.completions
          .filter((item) => String(item.userId) === String(user.id))
          .map((item) => item.sightId)
      : [],
  );
  const catalogSightIds = new Set(sights.map((item) => item.id));
  const visitedSightIds = new Set([
    ...[...completed].filter((id) => catalogSightIds.has(id)),
    ...visits.flatMap((visit) =>
      (visit.places ?? [])
        .filter((place) => place.type === "sight")
        .map((place) => place.id || place.name),
    ),
  ]);
  const managedCollections = db.data.managedCollections
    .filter(
      (collection) =>
        collection.isPublished !== false &&
        (collection.places ?? []).some(
          (place) =>
            String(place.country).toLocaleLowerCase() ===
            String(country.name).toLocaleLowerCase(),
        ),
    )
    .sort((a, b) => Number(a.displayOrder ?? 0) - Number(b.displayOrder ?? 0));
  return {
    isEnriching: automaticImports.has(code),
    country: {
      id: country.id,
      code: country.iso2,
      iso3: country.iso3,
      name: country.name,
      officialName: country.officialName,
      flag: country.flagUrl,
      capital: country.capital,
      population: country.population,
      languages: country.languages ?? [],
      currencies: country.currencies ?? [],
      continent: country.continent,
      region: country.region,
      description: country.description,
      coverImage: country.coverImageUrl ?? "",
    },
    featuredIn,
    cities: cities.map((city) => publicCity(city, catalog)),
    sights: sights.map((sight) => ({
      ...publicSight(sight, catalog),
      completed: completed.has(sight.id),
    })),
    collections: managedCollections,
    stats: {
      cities: new Set(visits.map((x) => x.cityId)).size,
      totalCities: cities.length,
      sights: visitedSightIds.size,
      totalSights: sights.length,
      airports: new Set(
        visits.flatMap((x) =>
          (x.places ?? [])
            .filter((p) => p.type === "airport")
            .map((p) => p.name),
        ),
      ).size,
      premiumSights: sights.filter((item) => item.isPremium === true).length,
    },
    visitedCities: [
      ...new Map(
        visits.map((x) => [x.cityId, { id: x.cityId, name: x.cityName }]),
      ).values(),
    ],
  };
}

app.get("/api/countries/:code", (req, res) => {
  const code = String(req.params.code).trim().toUpperCase();
  const payload = catalogCountryPayload(code, req);
  if (payload) return res.json(payload);
  if (!countries[code])
    return res.status(404).json({ message: "Country is not supported." });

  const failure = automaticImportFailures.get(code);
  if (failure && Date.now() - failure.failedAt < 30_000)
    return res.status(503).json({
      message: failure.message,
      retryAfterSeconds: 30,
    });

  automaticImportFailures.delete(code);
  startAutomaticImport(code);
  return res.status(202).json({
    status: "importing",
    code,
    message: "Country data is being prepared.",
  });
});
app.get("/api/countries/:code/cities", (req, res) => {
  const payload = catalogCountryPayload(
    String(req.params.code).toUpperCase(),
    req,
  );
  return payload
    ? res.json(payload.cities)
    : res.status(404).json({ message: "Country has not been imported." });
});
app.get("/api/city-image", async (req, res) => {
  const name = String(req.query?.name ?? "").trim();
  const country = String(req.query?.country ?? "").trim();
  const region = String(req.query?.region ?? "").trim();
  const latitude = Number(req.query?.latitude);
  const longitude = Number(req.query?.longitude);
  if (!name || !country) {
    return res.status(422).json({ message: "City name and country are required." });
  }
  const cacheKey = `${name.toLocaleLowerCase()}|${region.toLocaleLowerCase()}|${country.toLocaleLowerCase()}`;
  if (resolvedCityImages.has(cacheKey)) {
    return res.json({ image: resolvedCityImages.get(cacheKey) });
  }

  const matches = await wikipediaSearch(
    `${name} ${region} ${country} municipality`,
    {
    timeoutMs: 7_000,
    retries: 1,
    },
  ).catch(() => []);
  const normalizeName = (value) =>
    String(value)
      .normalize("NFD")
      .replace(/\p{Diacritic}/gu, "")
      .toLocaleLowerCase();
  const targetName = normalizeName(name);
  const photographs = matches.filter(
    (item) =>
      item.imageUrl &&
      (normalizeName(item.name) === targetName ||
        normalizeName(item.name).startsWith(`${targetName},`)),
  );
  const nearby =
    Number.isFinite(latitude) && Number.isFinite(longitude)
      ? photographs
          .filter(
            (item) =>
              Number.isFinite(item.latitude) &&
              Number.isFinite(item.longitude),
          )
          .map((item) => ({
            ...item,
            distance: Math.hypot(
              item.latitude - latitude,
              item.longitude - longitude,
            ),
          }))
          .filter((item) => item.distance < 0.75)
          .sort((a, b) => a.distance - b.distance)
      : [];
  let image = nearby[0]?.imageUrl ?? photographs[0]?.imageUrl ?? "";
  if (!image) {
    const commons = await commonsImageSearch(
      `${name} ${region} ${country}`,
      { timeoutMs: 7_000, retries: 1 },
    ).catch(() => ({}));
    image = commons.imageUrl ?? "";
  }
  if (image) resolvedCityImages.set(cacheKey, image);
  return res.json({ image });
});
app.get("/api/place-image", async (req, res) => {
  const name = String(req.query?.name ?? "").trim();
  const city = String(req.query?.city ?? "").trim();
  const country = String(req.query?.country ?? "").trim();
  if (!name || !country) {
    return res.status(422).json({ message: "Place name and country are required." });
  }
  const cacheKey = `${name.toLocaleLowerCase()}|${city.toLocaleLowerCase()}|${country.toLocaleLowerCase()}`;
  if (resolvedPlaceImages.has(cacheKey)) {
    return res.json({ image: resolvedPlaceImages.get(cacheKey) });
  }

  const matches = await wikipediaSearch(`${name} ${city} ${country}`, {
    timeoutMs: 7_000,
    retries: 1,
  }).catch(() => []);
  const normalize = (value) =>
    String(value).normalize("NFD").replace(/\p{Diacritic}/gu, "").toLocaleLowerCase();
  const target = normalize(name);
  const named = matches.filter(
    (item) =>
      item.imageUrl &&
      (normalize(item.name) === target || normalize(item.name).includes(target)),
  );
  let image = named[0]?.imageUrl ?? matches.find((item) => item.imageUrl)?.imageUrl ?? "";
  if (!image) {
    const commons = await commonsImageSearch(
      `${name} ${city} ${country} landmark photograph`,
      { timeoutMs: 7_000, retries: 1 },
    ).catch(() => ({}));
    image = commons.imageUrl ?? "";
  }
  if (image) resolvedPlaceImages.set(cacheKey, image);
  return res.json({ image });
});
app.get("/api/cities/:id", (req, res) => {
  const catalog = availableCatalogs().find((source) =>
    source.cities.some(
      (item) =>
        item.id === req.params.id ||
        item.slug === req.params.id ||
        item.geonamesId === req.params.id,
    ),
  );
  const city = catalog?.cities.find(
    (item) =>
      item.id === req.params.id ||
      item.slug === req.params.id ||
      item.geonamesId === req.params.id,
  );
  return city
    ? res.json({
        ...publicCity(city, catalog),
        sights: rankEntities(
          catalog.sights.filter((item) => item.cityId === city.id),
          20,
        ).map((sight) => publicSight(sight, catalog)),
      })
    : res.status(404).json({ message: "City not found." });
});
app.get("/api/cities/:id/sights", (req, res) => {
  const catalog = availableCatalogs().find((source) =>
    source.cities.some(
      (item) =>
        item.id === req.params.id ||
        item.slug === req.params.id ||
        item.geonamesId === req.params.id,
    ),
  );
  const city = catalog?.cities.find(
    (item) =>
      item.id === req.params.id ||
      item.slug === req.params.id ||
      item.geonamesId === req.params.id,
  );
  return city
    ? res.json(
        rankEntities(
          catalog.sights.filter((item) => item.cityId === city.id),
          20,
        ).map((sight) => publicSight(sight, catalog)),
      )
    : res.status(404).json({ message: "City not found." });
});
app.get("/api/sights/:id", (req, res) => {
  const catalog = availableCatalogs().find((source) =>
    source.sights.some(
      (item) =>
        item.id === req.params.id ||
        item.slug === req.params.id ||
        item.opentripmapXid === req.params.id,
    ),
  );
  const sight = catalog?.sights.find(
    (item) =>
      item.id === req.params.id ||
      item.slug === req.params.id ||
      item.opentripmapXid === req.params.id,
  );
  return sight
    ? res.json(publicSight(sight, catalog))
    : res.status(404).json({ message: "Sight not found." });
});

app.get("/daily-destinations", (req, res) => {
  const requestedDate = String(req.query?.date ?? "").trim();
  const items = db.data.dailyDestinations
    .filter(
      (item) =>
        item.isPublished !== false &&
        (!requestedDate || !item.publishDate || item.publishDate === requestedDate),
    )
    .sort((a, b) => Number(a.displayOrder ?? 0) - Number(b.displayOrder ?? 0));
  return res.json(items);
});

app.get("/api/collections/:id", (req, res) => {
  const item = db.data.managedCollections.find(
    (collection) =>
      collection.id === req.params.id && collection.isPublished !== false,
  );
  return item
    ? res.json(item)
    : res.status(404).json({ message: "Collection not found." });
});

app.get("/admin", async (_req, res) => {
  const html = await readFile(resolve(process.cwd(), "server/admin.html"), "utf8");
  return res.status(200).set("Content-Type", "text/html; charset=utf-8").send(html);
});

app.get("/admin/api/meta", (req, res) => {
  if (!requireAdmin(req, res)) return;
  return res.json({
    countries: db.data.countries.map((item) => ({
      id: item.id,
      code: item.iso2,
      name: item.name,
    })),
    cities: db.data.cities.map((item) => ({
      id: item.id,
      countryId: item.countryId,
      name: item.name,
    })),
  });
});

function adminSight(sight) {
  const country = db.data.countries.find((item) => item.id === sight.countryId);
  const city = db.data.cities.find((item) => item.id === sight.cityId);
  return {
    ...sight,
    image: sight.imageUrl ?? "",
    content: sight.description ?? "",
    country: country?.name ?? "",
    countryCode: country?.iso2 ?? "",
    city: city?.name ?? "",
    unlocked: sight.isPremium !== true,
  };
}

app.get("/admin/api/sights", (req, res) => {
  if (!requireAdmin(req, res)) return;
  return res.json(
    rankEntities(db.data.sights, db.data.sights.length).map(adminSight),
  );
});

app.post("/admin/api/sights", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const name = requiredText(req.body?.name, "Name", res);
  if (!name) return;
  const countryId = requiredText(req.body?.countryId, "Country", res);
  if (!countryId) return;
  const cityId = requiredText(req.body?.cityId, "City", res);
  if (!cityId) return;
  const country = db.data.countries.find((item) => item.id === countryId);
  const city = db.data.cities.find(
    (item) => item.id === cityId && item.countryId === countryId,
  );
  if (!country || !city)
    return res.status(422).json({ message: "Country or city is invalid." });
  const sight = {
    id: String(req.body?.id ?? "").trim() || randomUUID(),
    countryId,
    cityId,
    name,
    slug: slugify(req.body?.slug || name),
    description: String(req.body?.content ?? req.body?.description ?? "").trim(),
    category: String(req.body?.category ?? "attraction").trim() || "attraction",
    imageUrl: String(req.body?.image ?? req.body?.imageUrl ?? "").trim(),
    latitude: Number(req.body?.latitude ?? city.latitude ?? 0),
    longitude: Number(req.body?.longitude ?? city.longitude ?? 0),
    isFeatured: req.body?.isFeatured !== false,
    isPremium: req.body?.unlocked === false || req.body?.isPremium === true,
    displayOrder: Number(req.body?.displayOrder ?? db.data.sights.length),
    manualFields: ["name", "description", "category", "imageUrl", "isPremium"],
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
  if (db.data.sights.some((item) => item.id === sight.id))
    return res.status(409).json({ message: "Sight ID already exists." });
  db.data.sights.push(sight);
  await db.write();
  return res.status(201).json(adminSight(sight));
});

app.put("/admin/api/sights/:id", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const sight = db.data.sights.find((item) => item.id === req.params.id);
  if (!sight) return res.status(404).json({ message: "Sight not found." });
  const name = requiredText(req.body?.name, "Name", res);
  if (!name) return;
  const countryId = requiredText(req.body?.countryId, "Country", res);
  if (!countryId) return;
  const cityId = requiredText(req.body?.cityId, "City", res);
  if (!cityId) return;
  if (!db.data.cities.some((item) => item.id === cityId && item.countryId === countryId))
    return res.status(422).json({ message: "Country or city is invalid." });
  Object.assign(sight, {
    countryId,
    cityId,
    name,
    slug: slugify(req.body?.slug || name),
    description: String(req.body?.content ?? req.body?.description ?? "").trim(),
    category: String(req.body?.category ?? "attraction").trim() || "attraction",
    imageUrl: String(req.body?.image ?? req.body?.imageUrl ?? "").trim(),
    latitude: Number(req.body?.latitude ?? sight.latitude ?? 0),
    longitude: Number(req.body?.longitude ?? sight.longitude ?? 0),
    isFeatured: req.body?.isFeatured !== false,
    isPremium: req.body?.unlocked === false || req.body?.isPremium === true,
    displayOrder: Number(req.body?.displayOrder ?? sight.displayOrder ?? 0),
    updatedAt: new Date().toISOString(),
  });
  await db.write();
  return res.json(adminSight(sight));
});

app.delete("/admin/api/sights/:id", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const index = db.data.sights.findIndex((item) => item.id === req.params.id);
  if (index < 0) return res.status(404).json({ message: "Sight not found." });
  db.data.sights.splice(index, 1);
  db.data.imageCredits = db.data.imageCredits.filter(
    (item) => !(item.entityType === "sight" && item.entityId === req.params.id),
  );
  await db.write();
  return res.status(204).send();
});

function collectionPayload(body, existing = {}) {
  const title = String(body?.title ?? "").trim();
  return {
    ...existing,
    id: existing.id ?? (String(body?.id ?? "").trim() || randomUUID()),
    title,
    detail: String(body?.detail ?? body?.description ?? "").trim(),
    description: String(body?.description ?? body?.detail ?? "").trim(),
    imageUrl: String(body?.imageUrl ?? body?.image ?? "").trim(),
    isPublished: body?.isPublished !== false,
    isPremium: body?.unlocked === false || body?.isPremium === true,
    displayOrder: Number(body?.displayOrder ?? existing.displayOrder ?? 0),
    places: Array.isArray(body?.places)
      ? body.places.map((place) => ({
          id: String(place.id ?? "").trim() || randomUUID(),
          name: String(place.name ?? "").trim(),
          city: String(place.city ?? "").trim(),
          country: String(place.country ?? "").trim(),
          imageUrl: String(place.imageUrl ?? place.image ?? "").trim(),
          content: String(place.content ?? place.description ?? "").trim(),
          isPremium: place.unlocked === false || place.isPremium === true,
        }))
      : existing.places ?? [],
    createdAt: existing.createdAt ?? new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
}

app.get("/admin/api/collections", (req, res) => {
  if (!requireAdmin(req, res)) return;
  return res.json(db.data.managedCollections);
});
app.post("/admin/api/collections", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const item = collectionPayload(req.body);
  if (!item.title) return res.status(422).json({ message: "Title is required." });
  if (db.data.managedCollections.some((value) => value.id === item.id))
    return res.status(409).json({ message: "Collection ID already exists." });
  db.data.managedCollections.push(item);
  await db.write();
  return res.status(201).json(item);
});
app.put("/admin/api/collections/:id", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const index = db.data.managedCollections.findIndex((item) => item.id === req.params.id);
  if (index < 0) return res.status(404).json({ message: "Collection not found." });
  const item = collectionPayload(req.body, db.data.managedCollections[index]);
  if (!item.title) return res.status(422).json({ message: "Title is required." });
  db.data.managedCollections[index] = item;
  await db.write();
  return res.json(item);
});
app.delete("/admin/api/collections/:id", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const index = db.data.managedCollections.findIndex((item) => item.id === req.params.id);
  if (index < 0) return res.status(404).json({ message: "Collection not found." });
  db.data.managedCollections.splice(index, 1);
  db.data.collectionProgress = db.data.collectionProgress.filter(
    (item) => item.collectionId !== req.params.id,
  );
  await db.write();
  return res.status(204).send();
});

function dailyPayload(body, existing = {}) {
  return {
    ...existing,
    id: existing.id ?? (String(body?.id ?? "").trim() || randomUUID()),
    name: String(body?.name ?? "").trim(),
    country: String(body?.country ?? "").trim(),
    city: String(body?.city ?? "").trim(),
    imageUrl: String(body?.imageUrl ?? body?.image ?? "").trim(),
    icon: String(body?.icon ?? "🌍").trim() || "🌍",
    content: String(body?.content ?? "").trim(),
    question: String(body?.question ?? "").trim(),
    options: Array.isArray(body?.options)
      ? body.options.map((item) => String(item).trim()).filter(Boolean)
      : String(body?.options ?? "").split("\n").map((item) => item.trim()).filter(Boolean),
    correctAnswer: Number(body?.correctAnswer ?? 0),
    publishDate: String(body?.publishDate ?? "").trim(),
    isPublished: body?.isPublished !== false,
    isPremium: body?.unlocked === false || body?.isPremium === true,
    displayOrder: Number(body?.displayOrder ?? existing.displayOrder ?? 0),
    createdAt: existing.createdAt ?? new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
}
app.get("/admin/api/daily-destinations", (req, res) => {
  if (!requireAdmin(req, res)) return;
  return res.json(db.data.dailyDestinations);
});
app.post("/admin/api/daily-destinations", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const item = dailyPayload(req.body);
  if (!item.name || !item.country || !item.content || !item.question || item.options.length < 2)
    return res.status(422).json({ message: "Name, country, content, question, and at least two options are required." });
  if (item.correctAnswer < 0 || item.correctAnswer >= item.options.length)
    return res.status(422).json({ message: "Correct answer index is invalid." });
  db.data.dailyDestinations.push(item);
  await db.write();
  return res.status(201).json(item);
});
app.put("/admin/api/daily-destinations/:id", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const index = db.data.dailyDestinations.findIndex((item) => item.id === req.params.id);
  if (index < 0) return res.status(404).json({ message: "Daily destination not found." });
  const item = dailyPayload(req.body, db.data.dailyDestinations[index]);
  if (!item.name || !item.country || !item.content || !item.question || item.options.length < 2)
    return res.status(422).json({ message: "Name, country, content, question, and at least two options are required." });
  if (item.correctAnswer < 0 || item.correctAnswer >= item.options.length)
    return res.status(422).json({ message: "Correct answer index is invalid." });
  db.data.dailyDestinations[index] = item;
  await db.write();
  return res.json(item);
});
app.delete("/admin/api/daily-destinations/:id", async (req, res) => {
  if (!requireAdmin(req, res)) return;
  const index = db.data.dailyDestinations.findIndex((item) => item.id === req.params.id);
  if (index < 0) return res.status(404).json({ message: "Daily destination not found." });
  db.data.dailyDestinations.splice(index, 1);
  await db.write();
  return res.status(204).send();
});

app.get("/countries/:code", (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const code = String(req.params.code ?? "").toUpperCase();
  const countryIdentity = countries[code];
  const catalog = COUNTRY_CATALOG[code] ?? {
    name: countryIdentity?.name ?? code,
    flag: countryIdentity ? getEmojiFlag(code) : "🌍",
    heroCities: [],
    featuredIn: [],
    sights: [],
  };
  const visits = db.data.visits.filter(
    (visit) =>
      String(visit.userId) === String(user.id) && visit.countryCode === code,
  );
  const completedIds = new Set(
    db.data.completions
      .filter((completion) => String(completion.userId) === String(user.id))
      .map((completion) => String(completion.sightId)),
  );
  const visitedCities = [
    ...new Map(
      visits.map((visit) => [
        String(visit.cityId),
        { id: String(visit.cityId), name: String(visit.cityName) },
      ]),
    ).values(),
  ];
  const recordedSightIds = visits.flatMap((visit) =>
    (visit.places ?? [])
      .filter((place) => place.type === "sight")
      .map((place) => String(place.id)),
  );
  const airportNames = new Set(
    visits.flatMap((visit) =>
      (visit.places ?? [])
        .filter((place) => place.type === "airport")
        .map((place) => String(place.name)),
    ),
  );
  const catalogSightIds = new Set(catalog.sights.map((sight) => sight.id));
  const completedCountryIds = [...completedIds].filter((id) =>
    catalogSightIds.has(id),
  );
  return res.json({
    code,
    name: catalog.name,
    flag: catalog.flag,
    heroCities: catalog.heroCities,
    featuredIn: catalog.featuredIn,
    sights: catalog.sights.map((sight) => ({
      ...sight,
      premium: sight.premium === true,
      completed: completedIds.has(sight.id),
    })),
    stats: {
      cities: visitedCities.length,
      sights: new Set([...recordedSightIds, ...completedCountryIds]).size,
      airports: airportNames.size,
    },
    visitedCities,
  });
});

app.get("/collections", (req, res) => {
  const user = authenticatedUser(req);
  const status = String(req.query?.status ?? "all").toLocaleLowerCase();
  if (!["all", "active", "completed"].includes(status)) {
    return res
      .status(422)
      .json({ message: "Status must be all, active, or completed." });
  }
  const collections = collectionsFor(user?.id);
  return res.json(
    status === "all"
      ? collections
      : collections.filter((item) => item.status === status),
  );
});

app.put("/me/collections/:collectionId", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const collection = db.data.managedCollections.find(
    (item) => item.id === String(req.params.collectionId),
  );
  if (!collection)
    return res.status(404).json({ message: "Collection not found." });
  const progress = Number(req.body?.progress);
  if (!Number.isFinite(progress) || progress < 0 || progress > 100) {
    return res
      .status(422)
      .json({ message: "Progress must be between 0 and 100." });
  }
  let saved = db.data.collectionProgress.find(
    (item) =>
      String(item.userId) === String(user.id) &&
      item.collectionId === collection.id,
  );
  if (!saved) {
    saved = {
      id: randomUUID(),
      userId: user.id,
      collectionId: collection.id,
      progress: 0,
    };
    db.data.collectionProgress.push(saved);
  }
  saved.progress = progress;
  saved.updatedAt = new Date().toISOString();
  await db.write();
  return res.json(
    collectionsFor(user.id).find((item) => item.id === collection.id),
  );
});

app.put("/me/completions/:sightId", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const sightId = String(req.params.sightId);
  const index = db.data.completions.findIndex(
    (x) => x.userId === user.id && x.sightId === sightId,
  );
  if (req.body?.completed === false && index >= 0)
    db.data.completions.splice(index, 1);
  if (req.body?.completed !== false && index < 0)
    db.data.completions.push({
      id: randomUUID(),
      userId: user.id,
      sightId,
      completedAt: new Date().toISOString(),
    });
  await db.write();
  return res.json({ sightId, completed: req.body?.completed !== false });
});

app.put("/me/wishlist/:targetId", async (req, res) => {
  const user = requireUser(req, res);
  if (!user) return;
  const targetId = String(req.params.targetId);
  const index = db.data.wishlists.findIndex(
    (x) => x.userId === user.id && x.targetId === targetId,
  );
  if (req.body?.saved === false && index >= 0)
    db.data.wishlists.splice(index, 1);
  if (req.body?.saved !== false && index < 0)
    db.data.wishlists.push({
      id: randomUUID(),
      userId: user.id,
      targetId,
      savedAt: new Date().toISOString(),
    });
  await db.write();
  return res.json({ targetId, saved: req.body?.saved !== false });
});

app.use("/users", (_req, res) => {
  return res.status(404).json({ message: "Use the /auth endpoints." });
});

app.use(createApp(db));

app.listen(
  port,
  () => {
    console.log(`Stampo development API running at http://${host}:${port}`);
    console.log(
      "Auth: POST /auth/register, POST /auth/login, GET /auth/me, POST /auth/logout",
    );
  },
  host,
);
