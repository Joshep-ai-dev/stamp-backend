<?php

return [
    'concurrency' => (int) env('AI_CONTENT_CONCURRENCY', 5),
    'city_csv' => storage_path('app/imports/oxford-cities-2026.csv'),
];
