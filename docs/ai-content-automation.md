# AI content automation

The admin page has an **AI automation** tab. It generates missing descriptions and stamp images for existing countries, US states, ranked cities, and approved sights. Generated images use the stamp prompt and are saved as WebP under 299,000 bytes. Existing images and descriptions are preserved.

## Server setup

1. Set `OPENAI_API_KEY` in the server environment. Optional model settings are `OPENAI_TEXT_MODEL` and `OPENAI_IMAGE_MODEL`.
2. Run `php artisan migrate`.
3. Ensure PHP GD has WebP support (`imagewebp`).
4. Run a persistent worker: `php artisan queue:work database --queue=ai-content --tries=2 --timeout=600`. Keep `DB_QUEUE_RETRY_AFTER` greater than 600; the default in this project is 660.

Start with a small batch of 25 from the admin page. The page shows progress and failure details and supports pause and retry. Pausing stops queued work when the worker next picks it up; an in-flight API request may still finish.

For cities, upload the private Oxford CSV with `rank`, `city`, and `country` columns and ranks 1 through 1000. The file is read for the request only and is not stored publicly. Existing catalog cities are matched by country and normalized name; the most populous record is chosen when names repeat. Common name variants are mapped explicitly. Ranked cities absent from the catalog are added with stable `oxford-2026-{rank}` IDs so the full list can be processed.

To build top sights, run **Discover five sights per city** with the same CSV. The resulting sights are unfeatured so they can be reviewed in the Sights admin page. Mark approved sights as **Shown in lists**, then run **Existing top sights** to generate their descriptions and images. AI sight discovery can return incorrect attractions; check the names and locations before featuring them.
