<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class AiStampGenerator
{
    public function sights(string $city, string $country): array
    {
        $response = $this->request('/responses', [
            'model' => config('services.openai.text_model'),
            'input' => "Identify the top five distinct, real tourist attractions within {$city}, {$country}. Prioritize the most recognized and significant sights for visitors. Return only a JSON array of five short sight names. Do not invent attractions or choose sights outside this city. No markdown.",
        ]);
        $text = collect($response['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])
            ->where('type', 'output_text')->pluck('text')->implode("\n");
        $names = json_decode(trim($text), true);
        if (! is_array($names) || count($names) !== 5 || count(array_unique($names)) !== 5 ||
            collect($names)->contains(fn ($name) => ! is_string($name) || trim($name) === '' || mb_strlen($name) > 150)) {
            throw new RuntimeException('Sight discovery did not return five distinct names.');
        }

        return array_map('trim', $names);
    }

    public function description(string $category, string $name): string
    {
        $response = $this->request('/responses', [
            'model' => config('services.openai.text_model'),
            'input' => "Write a factual 90–150 word travel description for the {$category} {$name}. Explain its location, significance, and visitor highlights in two short paragraphs. Return only the description.",
        ]);
        $text = collect($response['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])
            ->where('type', 'output_text')->pluck('text')->implode("\n");
        if (trim($text) === '') {
            throw new RuntimeException('The text model returned no description.');
        }

        return trim($text);
    }

    public function imagePrompt(string $category, string $name, string $extra = ''): string
    {
        if ($category === 'Top Sight') {
            $titleInstruction = 'This is a top-sight stamp: include NO title, place name, city name, '
                .'country name, letters, numbers, signs, captions, or other visible text. '
                .'Let the engraving continue through the upper part of the frame. '
                .'Do not leave an empty title panel.';
        } else {
            $lastComma = strrpos($name, ', ');
            $title = in_array($category, ['City', 'State'], true) && $lastComma !== false
                ? substr($name, 0, $lastComma) : $name;
            $titleInstruction = 'At the top center print exactly "'.$title.'" and no other words or numbers. '
                .'Use bold old-style serif lettering, preserving this capitalization. '
                .'Keep the title prominent but within the inner border; for a long name '
                .'reduce its size to fit. Put a short thin horizontal rule on each side '
                .'of one tiny centered diamond ornament directly beneath the title.';
        }
        $subject = match ($category) {
            'Country' => 'Depict defining real landmarks, architecture, landscape, and plants from this country in one coherent panorama.',
            'State' => 'Depict defining real landmarks, architecture, landscape, and plants from this US state in one coherent panorama.',
            'City' => 'Show recognizable real landmarks in one geographically accurate city panorama.',
            'Top Sight' => 'Show this exact attraction as the central subject with accurate surroundings; the city and country identify its location only.',
        };
        $direction = trim($extra) ?: 'none';

        return <<<PROMPT
Generate the COMPLETE vintage postage-stamp image for {$name} ({$category}).
Use the same overall design for every place: warm aged cream paper, a finely
scalloped perforated paper edge, two thin rounded rectangular border lines,
and one richly detailed engraved landscape inside. Draw the whole stamp as
one coherent image; the app adds no border, lettering, or ornament afterward.

{$titleInstruction}

Use ONLY the reference image's color treatment: deep forest-green ink for the
engraving, title if present, double border, and the narrow background outside
the perforated paper. Use warm aged cream for the paper. Build depth with lighter
and darker tones of that same green. Do not use red, brown, blue, purple, teal,
or other colored inks. Keep this exact green-and-cream palette across all places.

Keep the same border proportions for countries, US states, cities, and sights.
At 1200x800, leave a consistent dark margin of roughly 12 pixels from each
canvas edge to the outer tips of the cream perforations. Then leave a consistent
cream strip of roughly 12 pixels from the inner base of the perforations to
the first ink border line. Use the same visual thickness on all four sides;
the top and bottom bands must not be taller than the side bands. Keep both
inner border lines thin and close together. Do not crop the perforations.
Draw approximately 30-35 small shallow scallops across each horizontal edge
and 24-25 down each vertical edge, with softly rounded corners.

{$subject} Use fine etched lines, dense cross-hatching, layered depth, subtle
aged-paper grain, and accurate local geography. Let the scene reach near the
inner border. Landscape 3:2 composition. No collage, invented landmarks,
modern bright colors, logo, or watermark.
Additional direction: {$direction}.
PROMPT;
    }

    public function image(string $category, string $name, string $folder, string $extra = ''): string
    {
        return $this->storeImage($this->request('/images/generations', $this->imageBody($category, $name, $extra)), $folder);
    }

    public function generateMany(array $tasks): array
    {
        $results = array_fill_keys(array_keys($tasks), []);
        $textRequests = [];
        foreach ($tasks as $id => $task) {
            if (($task['discover'] ?? false) || ($task['description'] ?? false)) {
                $textRequests[$id] = [
                    'path' => '/responses',
                    'body' => ['model' => config('services.openai.text_model'), 'input' => ($task['discover'] ?? false)
                        ? "Identify the top five distinct, real tourist attractions within {$task['name']}. Prioritize the most recognized and significant sights for visitors. Return only a JSON array of five short sight names. Do not invent attractions or choose sights outside this city. No markdown."
                        : "Write a factual 90–150 word travel description for the {$task['category']} {$task['name']}. Explain its location, significance, and visitor highlights in two short paragraphs. Return only the description."],
                ];
            }
        }
        foreach ($this->requestMany($textRequests) as $id => $response) {
            try {
                if ($response instanceof Throwable) {
                    throw $response;
                }
                $text = collect($response['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])
                    ->where('type', 'output_text')->pluck('text')->implode("\n");
                if ($tasks[$id]['discover'] ?? false) {
                    $names = json_decode(trim($text), true);
                    if (! is_array($names) || count($names) !== 5 ||
                        collect($names)->contains(fn ($name) => ! is_string($name) || trim($name) === '' || mb_strlen($name) > 150)) {
                        throw new RuntimeException('Sight discovery did not return five distinct names.');
                    }
                    $names = array_values(array_map('trim', $names));
                    if (count(array_unique($names)) !== 5) {
                        throw new RuntimeException('Sight discovery did not return five distinct names.');
                    }
                    $results[$id]['names'] = $names;
                } else {
                    if (trim($text) === '') {
                        throw new RuntimeException('The text model returned no description.');
                    }
                    $results[$id]['description'] = trim($text);
                }
            } catch (Throwable $exception) {
                $results[$id]['error'] = $exception;
            }
        }
        $imageRequests = [];
        foreach ($tasks as $id => $task) {
            if (($task['image'] ?? false) && ! isset($results[$id]['error'])) {
                $imageRequests[$id] = ['path' => '/images/generations', 'body' => $this->imageBody($task['category'], $task['name'])];
            }
        }
        foreach ($this->requestMany($imageRequests) as $id => $response) {
            try {
                if ($response instanceof Throwable) {
                    throw $response;
                }
                $results[$id]['image'] = $this->storeImage($response, $tasks[$id]['folder']);
            } catch (Throwable $exception) {
                $results[$id]['error'] = $exception;
            }
        }

        return $results;
    }

    private function imageBody(string $category, string $name, string $extra = ''): array
    {
        $model = config('services.openai.image_model');

        return [
            'model' => $model, 'prompt' => $this->imagePrompt($category, $name, $extra),
            'size' => str_starts_with($model, 'gpt-image-2') ? '1200x800' : '1536x1024',
            'quality' => 'high', 'output_format' => 'webp', 'output_compression' => 60,
        ];
    }

    private function requestMany(array $requests): array
    {
        if (! $requests) {
            return [];
        }
        $key = config('services.openai.api_key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY is missing.');
        }
        $results = [];
        $pending = $requests;
        for ($attempt = 1; $attempt <= 3 && $pending; $attempt++) {
            $responses = Http::pool(function (Pool $pool) use ($pending, $key): void {
                foreach ($pending as $id => $request) {
                    $pool->as((string) $id)->withToken($key)->acceptJson()->connectTimeout(15)->timeout(180)->retry(3, 2000)
                        ->post(rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/').$request['path'], array_merge($request['body'], ['stream' => false]));
                }
            }, max(1, min(5, (int) config('ai.concurrency', 3))));
            $retry = [];
            foreach ($responses as $id => $response) {
                try {
                    if ($response instanceof Throwable) {
                        throw $response;
                    }
                    $results[$id] = $this->responseData($response);
                } catch (UnexpectedValueException $exception) {
                    $results[$id] = $exception;
                    if ($attempt < 3) {
                        $retry[$id] = $pending[$id];
                    } else {
                        Log::warning('OpenAI response could not be decoded after three attempts.', [
                            'endpoint' => $pending[$id]['path'], 'error' => $exception->getMessage(),
                        ]);
                    }
                } catch (Throwable $exception) {
                    $results[$id] = $exception;
                }
            }
            $pending = $retry;
            if ($pending) {
                Sleep::for($attempt)->seconds();
            }
        }

        return $results;
    }

    public function storeImage(array $response, string $folder): string
    {
        $encoded = $response['data'][0]['b64_json'] ?? null;
        $bytes = $encoded ? base64_decode($encoded, true) : false;
        $details = $bytes ? @getimagesizefromstring($bytes) : false;
        $extension = match ($details['mime'] ?? '') {
            'image/webp' => 'webp', 'image/png' => 'png', 'image/jpeg' => 'jpg', default => null,
        };
        if (! $bytes || ! $extension) {
            throw new RuntimeException('The image model returned no readable image.');
        }
        if (function_exists('imagecreatefromstring') && function_exists('imagewebp') && ($extension !== 'webp' || strlen($bytes) >= 299000)) {
            $source = @imagecreatefromstring($bytes);
            if (! $source) {
                throw new RuntimeException('The image model returned an unreadable image.');
            }
            try {
                $bytes = $this->smallWebp($source);
                $extension = 'webp';
            } finally {
                imagedestroy($source);
            }
        }
        $directory = public_path('images/'.$folder);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the image directory.');
        }
        $filename = Str::uuid().'.'.$extension;
        if (file_put_contents($directory.'/'.$filename, $bytes) === false) {
            throw new RuntimeException('Could not store the generated image.');
        }

        return '/images/'.$folder.'/'.$filename;
    }

    private function smallWebp(\GdImage $source): string
    {
        $originalWidth = imagesx($source);
        $originalHeight = imagesy($source);
        foreach ([1200, 1100, 1000, 900, 800] as $width) {
            $width = min($width, $originalWidth);
            $height = (int) round($originalHeight * $width / $originalWidth);
            $resized = imagescale($source, $width, $height, IMG_BICUBIC);
            if (! $resized) {
                continue;
            }
            try {
                foreach ([85, 80, 75, 70, 65, 60, 55, 50, 45, 40, 35, 30] as $quality) {
                    ob_start();
                    imagewebp($resized, null, $quality);
                    $bytes = ob_get_clean();
                    if (is_string($bytes) && strlen($bytes) < 299000) {
                        return $bytes;
                    }
                }
            } finally {
                imagedestroy($resized);
            }
        }
        throw new RuntimeException('Could not encode the generated image under 299 KB.');
    }

    private function request(string $path, array $body): array
    {
        $result = $this->requestMany([['path' => $path, 'body' => $body]])[0];
        if ($result instanceof Throwable) {
            throw $result;
        }

        return $result;
    }

    private function responseData(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('OpenAI API returned HTTP '.$response->status().': '.Str::limit((string) ($response->json('error.message') ?? $response->body()), 300));
        }
        $body = trim($response->body());
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = trim(substr($body, 3));
        }
        $data = json_decode($body, true);
        $reason = $body === '' ? 'empty response body' : json_last_error_msg();
        if (! is_array($data) && (str_contains(strtolower($response->header('Content-Type') ?? ''), 'text/event-stream') || str_starts_with($body, 'event:') || str_starts_with($body, 'data:'))) {
            $data = $this->streamData($body);
            $reason = 'event stream ended without a completed response';
        }
        if (! is_array($data)) {
            throw new UnexpectedValueException('OpenAI returned an unreadable response after HTTP '.$response->status()
                .' ('.($response->header('Content-Type') ?: 'unknown content type').', '.strlen($body).' bytes; '.$reason.').'
                .($response->header('x-request-id') ? ' Request ID: '.$response->header('x-request-id').'.' : ''));
        }

        return $data;
    }

    private function streamData(string $body): ?array
    {
        foreach (preg_split('/\r?\n\r?\n/', $body) as $block) {
            $lines = [];
            foreach (preg_split('/\r?\n/', $block) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $lines[] = ltrim(substr($line, 5), ' ');
                }
            }
            $event = json_decode(implode("\n", $lines), true);
            if (! is_array($event)) {
                continue;
            }
            if (($event['type'] ?? '') === 'response.completed' && is_array($event['response'] ?? null)) {
                return $event['response'];
            }
            if (($event['type'] ?? '') === 'image_generation.completed' && is_string($event['b64_json'] ?? null)) {
                return ['data' => [['b64_json' => $event['b64_json']]]];
            }
            if (in_array($event['type'] ?? '', ['error', 'response.failed', 'response.incomplete'], true)) {
                throw new RuntimeException('OpenAI generation did not complete: '.Str::limit((string) ($event['message'] ?? $event['response']['error']['message'] ?? $event['response']['incomplete_details']['reason'] ?? 'stream error'), 300));
            }
        }

        return null;
    }
}
