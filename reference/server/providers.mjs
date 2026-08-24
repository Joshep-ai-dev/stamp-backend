import { cleanDescription } from "./lib/catalog.mjs";
import { fetchJson } from "./lib/http.mjs";

const NON_PHOTO_IMAGE =
  /\b(flag|logo|map|diagram|coat[_ -]?of[_ -]?arms|emblem|locator|icon|seal)\b/i;

export function wikipediaPhotoUrl(data) {
  const source = data?.thumbnail?.source || data?.originalimage?.source || "";
  if (!source || NON_PHOTO_IMAGE.test(decodeURIComponent(source))) return "";
  if (!source.includes("upload.wikimedia.org")) return source;
  try {
    const url = new URL(source);
    const parts = url.pathname.split("/").filter(Boolean);
    const thumbIndex = parts.indexOf("thumb");
    const filename = decodeURIComponent(
      thumbIndex >= 0 ? parts[thumbIndex + 3] : parts.at(-1),
    );
    if (!filename || filename.toLowerCase().endsWith(".svg")) return "";
    return `https://commons.wikimedia.org/wiki/Special:Redirect/file/${encodeURIComponent(filename)}?width=1200`;
  } catch {
    return source;
  }
}

export async function restCountry(iso2, options) {
  const row = await fetchJson(
    `https://countries.dev/alpha/${encodeURIComponent(iso2)}`,
    options,
  );
  if (!row?.alpha2Code || !row?.name)
    throw new Error("The country provider returned an invalid country.");
  return {
    iso2: row.alpha2Code,
    iso3: row.alpha3Code,
    name: row.name,
    officialName: row.officialName ?? row.name,
    capital: row.capital ?? "",
    population: Number(row.population ?? 0),
    languages: (row.languages ?? []).map((x) => x.name ?? x),
    currencies: (row.currencies ?? []).map((x) => x.name ?? x),
    continent: row.region ?? "",
    region: row.subregion ?? row.region ?? "",
    flagUrl: row.flag ?? "",
  };
}

export async function geonamesCities(
  iso2,
  _username = process.env.GEONAMES_USERNAME,
  options,
) {
  const rows = await fetchJson(
    `https://countries.dev/cities?country=${encodeURIComponent(iso2)}&limit=10`,
    options,
  );
  if (!Array.isArray(rows)) return [];
  return rows
    .map((x) => ({
      geonamesId: String(x.geonameId ?? ""),
      name: x.name,
      population: Number(x.population ?? 0),
      latitude: Number(x.latitude),
      longitude: Number(x.longitude),
      adminName: x.admin1Code ?? "",
    }))
    .filter((x) => x.geonamesId && Number.isFinite(x.latitude));
}

export async function wikipediaSummary(title, options) {
  if (!title) return {};
  const data = await fetchJson(
    `https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(title.replaceAll(" ", "_"))}`,
    options,
  );
  return {
    wikipediaTitle: data.title ?? title,
    wikidataId: data.wikibase_item ?? null,
    description: cleanDescription(data.extract),
    imageUrl: wikipediaPhotoUrl(data),
    sourceUrl: data.content_urls?.desktop?.page ?? "",
  };
}

export async function wikipediaSearch(query, options) {
  if (!query) return [];
  const params = new URLSearchParams({
    action: "query",
    format: "json",
    generator: "search",
    gsrsearch: query,
    gsrnamespace: "0",
    gsrlimit: "6",
    prop: "extracts|pageimages|coordinates|pageprops|info",
    exintro: "1",
    explaintext: "1",
    exsentences: "2",
    piprop: "original|thumbnail",
    pithumbsize: "2000",
    inprop: "url",
    redirects: "1",
    origin: "*",
  });
  const data = await fetchJson(
    `https://en.wikipedia.org/w/api.php?${params}`,
    options,
  );
  return Object.values(data?.query?.pages ?? {})
    .sort((a, b) => Number(a.index ?? 999) - Number(b.index ?? 999))
    .map((page, index) => ({
      wikipediaTitle: page.title,
      wikidataId: page.pageprops?.wikibase_item ?? null,
      name: page.title,
      description: cleanDescription(page.extract),
      imageUrl: wikipediaPhotoUrl(page),
      sourceUrl: page.fullurl ?? "",
      latitude: Number(page.coordinates?.[0]?.lat),
      longitude: Number(page.coordinates?.[0]?.lon),
      score: 100 - index,
    }));
}

export async function commonsMetadata(imageUrl, options) {
  if (!imageUrl?.includes("upload.wikimedia.org")) return {};
  const filename = decodeURIComponent(
    new URL(imageUrl).pathname.split("/").pop(),
  );
  const data = await fetchJson(
    `https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=imageinfo&iiprop=url|extmetadata&titles=File:${encodeURIComponent(filename)}&origin=*`,
    options,
  );
  const page = Object.values(data?.query?.pages ?? {})[0];
  const info = page?.imageinfo?.[0];
  const meta = info?.extmetadata ?? {};
  return {
    sourceUrl: info?.url ?? imageUrl,
    filePageUrl: info?.descriptionurl ?? "",
    creator: cleanDescription(meta.Artist?.value, 1),
    license: meta.LicenseShortName?.value ?? "",
    licenseUrl: meta.LicenseUrl?.value ?? "",
    attribution: cleanDescription(
      meta.Attribution?.value || meta.Credit?.value,
      1,
    ),
  };
}

export async function commonsImageSearch(query, options) {
  if (!query) return {};
  const params = new URLSearchParams({
    action: "query",
    format: "json",
    generator: "search",
    gsrsearch: query,
    gsrnamespace: "6",
    gsrlimit: "8",
    prop: "imageinfo",
    iiprop: "url|extmetadata|mime",
    iiurlwidth: "1200",
    origin: "*",
  });
  const data = await fetchJson(
    `https://commons.wikimedia.org/w/api.php?${params}`,
    options,
  );
  const pages = Object.values(data?.query?.pages ?? {});
  const page = pages.find((item) => {
    const info = item.imageinfo?.[0];
    return info?.mime?.startsWith("image/") && info.mime !== "image/svg+xml";
  });
  const info = page?.imageinfo?.[0];
  if (!info) return {};
  const meta = info.extmetadata ?? {};
  return {
    imageUrl: info.thumburl ?? info.url ?? "",
    sourceUrl: info.url ?? "",
    filePageUrl: info.descriptionurl ?? "",
    creator: cleanDescription(meta.Artist?.value, 1),
    license: meta.LicenseShortName?.value ?? "",
    licenseUrl: meta.LicenseUrl?.value ?? "",
    attribution: cleanDescription(
      meta.Attribution?.value || meta.Credit?.value,
      1,
    ),
  };
}

export async function wikidataSights(country, options) {
  if (!country?.wikidataId) return [];
  const query = `SELECT DISTINCT ?item ?itemLabel ?coord ?article ?typeLabel ?sitelinks WHERE {
    VALUES ?type { wd:Q570116 wd:Q33506 wd:Q16970 wd:Q23413 wd:Q4989906 wd:Q811979 wd:Q839954 wd:Q174782 }
    ?item wdt:P17 wd:${country.wikidataId}; wdt:P31 ?type; wdt:P625 ?coord; wikibase:sitelinks ?sitelinks.
    ?article schema:about ?item; schema:isPartOf <https://en.wikipedia.org/>.
    SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
  } ORDER BY DESC(?sitelinks) LIMIT 30`;
  const data = await fetchJson(
    `https://query.wikidata.org/sparql?format=json&query=${encodeURIComponent(query)}`,
    {
      ...options,
      headers: {
        Accept: "application/sparql-results+json",
        ...(options?.headers ?? {}),
      },
    },
  );
  return (data?.results?.bindings ?? [])
    .map((row) => {
      const point = row.coord?.value?.match(/Point\(([-\d.]+) ([-\d.]+)\)/);
      return {
        wikidataId: row.item?.value?.split("/").pop(),
        wikipediaTitle: decodeURIComponent(
          row.article?.value?.split("/wiki/").pop() ?? "",
        ).replaceAll("_", " "),
        name: row.itemLabel?.value,
        category: row.typeLabel?.value ?? "attraction",
        longitude: Number(point?.[1]),
        latitude: Number(point?.[2]),
        score: Number(row.sitelinks?.value ?? 0),
      };
    })
    .filter(
      (item) => item.wikidataId && item.name && Number.isFinite(item.latitude),
    );
}
