const cache = new Map();

export async function fetchJson(
  url,
  {
    timeoutMs = 8000,
    retries = 2,
    cacheMs = 3600000,
    fetchImpl = fetch,
    headers = {},
  } = {},
) {
  const cached = cache.get(url);
  if (cached && cached.expires > Date.now()) return cached.value;
  let lastError;
  for (let attempt = 0; attempt <= retries; attempt += 1) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    try {
      const response = await fetchImpl(url, {
        signal: controller.signal,
        headers: {
          Accept: "application/json",
          "User-Agent": "StampoTravel/1.0",
          ...headers,
        },
      });
      if (!response.ok)
        throw new Error(`${response.status} ${response.statusText}`);
      const value = await response.json();
      cache.set(url, { value, expires: Date.now() + cacheMs });
      return value;
    } catch (error) {
      lastError = error;
      if (attempt < retries)
        await new Promise((resolve) => setTimeout(resolve, 250 * 2 ** attempt));
    } finally {
      clearTimeout(timeout);
    }
  }
  throw lastError;
}
