# AI content automation

The admin page has an **AI automation** tab. It generates missing descriptions and stamp images for existing countries, US states, ranked cities, and approved sights. Generated images use the stamp prompt and are requested as compressed WebP and saved automatically under `public/images`. When PHP GD supports WebP, oversized files are optimized below 299,000 bytes; otherwise the original valid API image is retained. Existing images and descriptions are preserved.

## Server setup

1. Set `OPENAI_API_KEY` in the server environment. Optional model settings are `OPENAI_TEXT_MODEL` and `OPENAI_IMAGE_MODEL`.
2. Run `php artisan migrate`.
3. PHP GD with WebP support is optional for additional image optimization. Generated image bytes can be saved without GD.
4. Open **AI automation** and start a batch. The page processes groups of three items using parallel OpenAI HTTP requests through authenticated requests. No separate queue worker or terminal command is required, and `QUEUE_CONNECTION` does not affect automation.

Keep the automation page open while processing. Closing it or switching to another admin tab stops further items after the current request finishes. Returning to automation continues running batches automatically, including previously queued batches. **Pause** stops the next item; **Resume** restarts paused or failed work. Multiple open pages cannot process the same item concurrently. Requests may take several minutes while images generate; configure the hosting server to allow long requests if necessary.

Start with a small batch of 25. Progress and failures update after each group. Set `AI_CONTENT_CONCURRENCY` from 1 to 5 to adjust simultaneous API requests (default 3). Results remain ordered by item ID. A failed item records its error and processing continues; use **Retry failed** after fixing the cause.

For cities, automation reads `storage/app/imports/oxford-cities-2026.csv` directly; no browser upload is needed. The private project copy must have `rank`, `city`, and `country` columns and ranks 1 through 1000. The file stays outside the public directory and is excluded from Git. Copy it to the same path when deploying. Existing catalog cities are matched by country and normalized name; the most populous record is chosen when names repeat. Common name variants are mapped explicitly. Ranked cities absent from the catalog are added with stable `oxford-2026-{rank}` IDs so the full list can be processed.

To build top sights, run **Discover five sights per city** using the same project CSV. Descriptions and stamp images are generated and saved for the discovered sights. The resulting sights are unfeatured so they can be reviewed in the Sights admin page. Mark approved sights as **Shown in lists**, then run **Existing top sights** to generate their descriptions and images. AI sight discovery can return incorrect attractions; check the names and locations before featuring them.

Use **View results** on a batch to browse every item in pages of five, including its current saved image, full description, status, and error. Click images to zoom. Open results refresh every 15 seconds while the tab is visible. These are current catalog values, not historical snapshots. Discovery results show the city’s current sights; discovery generates names, descriptions, and stamp images.

Results use compact rows with image previews and expandable descriptions. Discovery sights are grouped under collapsible city headings. **Edit** changes the name, description, image, and sight approval status directly in automation. **Remove** deletes a sight and cancels its pending generation entries; **Clear content** removes a destination’s image and description. Pause pending work and wait for in-flight generation before editing or removing the same record. The stamp generator uses the supplied forest-green-and-cream prompt, including the text-free Top Sight variant. Existing generated images retain their previous appearance until replaced.

Each discovery city heading also has **Remove** to remove that city’s entry from the batch and update its counters. Saved catalog cities and sights remain available. Removing an entry also cancels its queued job; in-flight work must finish first.

Completed batches have **Generate missing content** to fill missing images or descriptions. Existing discovered sight names and saved fields are reused, so this does not recreate removed sights or overwrite reviewed content. Image failures appear in the item errors. Generated images are saved automatically; a separate upload is not needed.
