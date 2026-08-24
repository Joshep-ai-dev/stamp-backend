import { randomUUID } from "node:crypto";

export const CATALOG_DEFAULTS = {
  countries: [],
  cities: [],
  sights: [],
  collections: [],
  countryCollections: [],
  imageCredits: [],
  importCache: [],
  importRuns: [],
};

export function ensureCatalog(db) {
  for (const [key, value] of Object.entries(CATALOG_DEFAULTS))
    db.data[key] ??= [...value];
}

export function slugify(value) {
  return String(value ?? "")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

export function countryFeatureCollections(country, sights = []) {
  const features = [];
  const region = country.region || country.continent || "World";
  const continent = country.continent || "World";
  features.push({
    name: `${region} Highlights`,
    slug: `${slugify(region)}-highlights`,
    icon: "🧭",
  });
  if (
    sights.some((sight) =>
      /museum|historic|castle|palace|monument/i.test(sight.category),
    )
  )
    features.push({
      name: "Cultural Icons",
      slug: "cultural-icons",
      icon: "🏛️",
    });
  if (
    sights.some((sight) =>
      /park|nature|mountain|beach|garden/i.test(sight.category),
    )
  )
    features.push({
      name: "Natural Wonders",
      slug: "natural-wonders",
      icon: "🌿",
    });
  features.push({
    name: `${continent} Explorer`,
    slug: `${slugify(continent)}-explorer`,
    icon: "✨",
  });
  return features.slice(0, 3);
}

export function cleanDescription(value, maxSentences = 2) {
  const clean = String(value ?? "")
    .replace(/<[^>]+>/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  return (clean.match(/[^.!?]+[.!?]+|[^.!?]+$/g) ?? [])
    .slice(0, maxSentences)
    .join(" ")
    .replace(/\s+/g, " ")
    .trim();
}

export function rankEntities(items, limit) {
  return [...items]
    .sort(
      (a, b) =>
        (a.displayOrder ?? 9999) - (b.displayOrder ?? 9999) ||
        Number(b.population ?? b.score ?? 0) -
          Number(a.population ?? a.score ?? 0) ||
        String(a.name).localeCompare(String(b.name)),
    )
    .slice(0, limit);
}

export function rankSightsWithCityCoverage(items, cityIds, limit = 20) {
  const ranked = rankEntities(items, items.length);
  const selected = ranked.slice(0, limit);
  const counts = () =>
    selected.reduce((result, item) => {
      result.set(item.cityId, (result.get(item.cityId) ?? 0) + 1);
      return result;
    }, new Map());

  for (const cityId of cityIds) {
    if (selected.some((item) => item.cityId === cityId)) continue;
    const candidate = ranked.find((item) => item.cityId === cityId);
    if (!candidate) continue;
    const cityCounts = counts();
    const replaceAt = selected.findLastIndex(
      (item) => (cityCounts.get(item.cityId) ?? 0) > 1,
    );
    if (replaceAt >= 0) selected[replaceAt] = candidate;
  }

  const selectedIds = new Set(selected.map((item) => item.id));
  return ranked.filter((item) => selectedIds.has(item.id)).slice(0, limit);
}

export function dedupeByStableId(items, keys) {
  const seen = new Set();
  return items.filter((item) => {
    const key = keys.map((field) => item[field]).find(Boolean);
    const stable = key
      ? String(key).toLowerCase()
      : `${slugify(item.name)}:${Number(item.latitude).toFixed(3)}:${Number(item.longitude).toFixed(3)}`;
    if (seen.has(stable)) return false;
    seen.add(stable);
    return true;
  });
}

export function upsertImported(list, match, incoming) {
  const existing = list.find(match);
  if (!existing) {
    const created = {
      id: incoming.id ?? randomUUID(),
      ...incoming,
      manualFields: incoming.manualFields ?? [],
    };
    list.push(created);
    return created;
  }
  const manual = new Set(existing.manualFields ?? []);
  for (const [key, value] of Object.entries(incoming))
    if (!manual.has(key) && value !== undefined) existing[key] = value;
  return existing;
}

export function imageCredit(entityType, entityId, image = {}) {
  if (!image.sourceUrl && !image.filePageUrl) return null;
  return {
    entityType,
    entityId,
    sourceUrl: image.sourceUrl ?? image.url ?? "",
    filePageUrl: image.filePageUrl ?? "",
    creator: image.creator ?? "",
    license: image.license ?? "",
    licenseUrl: image.licenseUrl ?? "",
    attribution: image.attribution ?? "",
  };
}
