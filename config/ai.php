<?php

return [
    'concurrency' => (int) env('AI_CONTENT_CONCURRENCY', 3),
    'image_quality' => env('AI_IMAGE_QUALITY', 'low'),
    'text_max_output_tokens' => (int) env('AI_TEXT_MAX_OUTPUT_TOKENS', 1024),
    'text_reasoning_effort' => env('AI_TEXT_REASONING_EFFORT', 'minimal'),
    'city_csv' => storage_path('app/imports/oxford-cities-2026.csv'),
];
