<?php

namespace App\Services;

use App\Exceptions\AiRateLimitedException;
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
    public function lesson(string $country, int $count): array
    {
        $response = $this->request('/responses', [
            'model' => config('services.openai.text_model'),
            'input' => "Create exactly {$count} distinct, factual travel and culture quiz questions about {$country} for a general audience. "
                .'Return only a JSON array. Each item must have information (2 concise, natural sentences shown before the question), prompt (one clear question), answers (exactly 4 short, distinct choices), correctAnswer (zero-based integer index), explanation (one sentence shown after answering that explains why the correct answer is right). '
                .'The information must include an indirect, descriptive clue to the correct answer, woven into natural background sentences. Describe the subject through its characteristics, history, location, or role without naming the subject being asked about or including the exact correct answer choice. '
                .'Users must be able to infer the correct choice from the information and answer options. The question must test recognition or understanding of those clues, rather than simple text matching or unrelated outside knowledge. Do not provide unrelated background, repeat the question, or label a sentence as a hint or answer. '
                .'For example, information about an unnamed wrought-iron landmark built in Paris for the 1889 World Fair can support a question asking which landmark it is, but the information must not say Eiffel Tower. '
                .'State the exact correct answer and the direct reasoning only in the explanation shown after answering. Each question must have exactly one unambiguous, factually correct answer. '
                .'Before returning, review each information/question pair: the information must support identifying the correct choice without naming it or copying its text. Rewrite pairs that lack a relevant clue or explicitly reveal the answer. '
                .'Avoid disputed facts, trivia requiring current data, and invented details. No markdown.',
        ]);
        $text = collect($response['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])
            ->where('type', 'output_text')->pluck('text')->implode("\n");
        $questions = json_decode(trim($text), true);
        if (! is_array($questions) || count($questions) !== $count) {
            throw new RuntimeException('The AI did not return the requested number of questions. Try again.');
        }
        foreach ($questions as $question) {
            if (! is_array($question) || count($question['answers'] ?? []) !== 4
                || ! is_int($question['correctAnswer'] ?? null) || $question['correctAnswer'] < 0 || $question['correctAnswer'] > 3
                || count(array_unique($question['answers'])) !== 4) {
                throw new RuntimeException('The AI returned an invalid quiz. Try again.');
            }
            foreach (['information', 'prompt', 'explanation'] as $field) {
                if (! is_string($question[$field] ?? null) || trim($question[$field]) === '') {
                    throw new RuntimeException('The AI returned an incomplete quiz. Try again.');
                }
            }
            foreach ($question['answers'] as $answer) {
                if (! is_string($answer) || trim($answer) === '') {
                    throw new RuntimeException('The AI returned an invalid answer. Try again.');
                }
            }
        }

        return $questions;
    }
    public static function concurrency(): int
    {
        return AiRateLimit::concurrency(max(1, min(12, (int) config('ai.concurrency', 8))));
    }

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
        if ($category === 'Collection Badge') {
            $subjectDirection = trim($extra) ?: 'the collection theme';

            return <<<PROMPT
Create one isolated vintage travel badge in the style of an old passport stamp or souvenir stamp.
FORMAT: Vertical rounded-rectangle badge, approximately 2:3. Small final appearance, around 300 × 450 px. Low-detail, low-resolution print appearance. Transparent background outside the badge.
BADGE SHAPE: The entire top section must be fully rounded into a continuous semicircular dome. No flat top or slightly rounded square corners. The sides rise vertically and smoothly join one large arch. Round the bottom corners less strongly. Use a thin double-line border following the exact silhouette.
BACKGROUND: Warm light cream paper inside the badge only. The entire area outside the outer border must have true transparent alpha. No canvas, page, wall, mockup, scenery, or external shadow. Show one badge only.
COLOR: One dark ink color only, preferably navy blue, forest green, dark brown, or muted red. Do not mix ink colors. Cream is the only interior background color.
STYLE: Classic 1950s to 1970s tourism stamp, old passport stamp, vintage souvenir badge, letterpress, screen-print texture, woodcut or engraved illustration. Simple hand-drawn lines, faded uneven ink, tiny missing ink areas, rough edges, soft paper grain, imperfect registration. Old and cheaply printed. Avoid polished vector style, high-detail realism, photography, gradients, 3D, gloss, and realistic lighting.
LAYOUT: Center the title "{$name}" near the upper rounded section in bold vintage serif type, with clear space from the border. In the middle, draw one recognizable landmark or subject representing {$name} as simple engraved line art. Use this context for the illustration only: {$subjectDirection}. Keep it legible at small size, with thick enough lines and few architectural details. Supporting clouds, waves, mountains, or trees are allowed only if useful. In the bottom area, print exactly "Collect the world" in centered bold vintage serif text. This bottom label has 15 letters. Use only the supplied title and bottom label; invent no other labels. Do not print the illustration context.
COMPOSITION: The illustration occupies 50 to 60 percent of badge height, the title 15 to 20 percent, and the bottom text 15 to 20 percent. Balance empty space. Keep everything inside the double border.
TYPOGRAPHY: Slightly condensed old serif lettering with faded, rough print edges. Keep every word readable. Do not distort letters.
QUALITY: Deliberately small and slightly low quality, mildly blurry print edges, low-detail engraving, subtle grain and imperfections, like a scanned vintage souvenir badge.
FINAL REQUIREMENT: One vertical badge with a fully rounded dome top, rounded bottom corners, double-line border, cream interior, one dark ink color, simple engraved subject, vintage serif text, worn print texture, and a fully transparent outer background.
PROMPT;
        }
        if (in_array($category, ['Top Sight', 'Collection Place', 'Quiz Image'], true)) {
            $titleInstruction = 'Include NO title, place name, city name, '
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
            'Collection' => 'Depict a coherent panorama of real places connected to this travel collection theme.',
            'Collection Place' => 'Depict the named place accurately as the central subject with its real surroundings.',
            'Quiz Image' => 'Depict the place or subject described here accurately. Do not reveal the answer using text or symbols.',
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
DO NOT show visible genitals or explicit nudity or sexual part.
Additional direction: {$direction}.
PROMPT;
    }

    public function image(string $category, string $name, string $folder, string $extra = ''): string
    {
        return $this->storeImage($this->request('/images/generations', $this->imageBody($category, $name, $extra)), $folder, $category === 'Collection Badge');
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
        foreach (array_chunk($imageRequests, self::concurrency(), true) as $group) {
            foreach ($this->requestMany($group) as $id => $response) {
                try {
                    if ($response instanceof Throwable) {
                        throw $response;
                    }
                    $results[$id]['image'] = $this->storeImage($response, $tasks[$id]['folder']);
                } catch (Throwable $exception) {
                    $results[$id]['error'] = $exception;
                }
            }
        }

        return $results;
    }

    private function imageBody(string $category, string $name, string $extra = ''): array
    {
        $model = config('services.openai.image_model');

        $badge = $category === 'Collection Badge';
        $body = [
            'model' => $model, 'prompt' => $this->imagePrompt($category, $name, $extra),
            'size' => $badge ? (str_starts_with($model, 'gpt-image-2') ? '800x1200' : '1024x1536') : (str_starts_with($model, 'gpt-image-2') ? '1200x800' : '1536x1024'),
            'quality' => config('ai.image_quality', 'low'),
            'output_format' => $badge ? 'png' : 'webp', 'output_compression' => $badge ? 100 : 60,
        ];
        if ($badge) {
            $body['background'] = 'transparent';
        }

        return $body;
    }

    private function textOptions(array $body): array
    {
        $body['max_output_tokens'] = max(256, (int) config('ai.text_max_output_tokens', 1024));
        if (preg_match('/^gpt-5(?:-(?:mini|nano))?(?:-\d{4}-\d{2}-\d{2})?$/', (string) $body['model'])) {
            $body['reasoning'] = ['effort' => config('ai.text_reasoning_effort', 'minimal')];
        }

        return $body;
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
        $delay = AiRateLimit::retryAfter();
        if ($delay > 0) {
            return array_fill_keys(array_keys($requests), new AiRateLimitedException(now()->timestamp + $delay));
        }
        $results = [];
        $pending = $requests;
        for ($attempt = 1; $attempt <= 3 && $pending; $attempt++) {
            if ($attempt > 1 && AiRateLimit::retryAfter() > 0) {
                foreach ($pending as $id => $request) {
                    $results[$id] = new AiRateLimitedException(now()->timestamp + AiRateLimit::retryAfter());
                }
                break;
            }
            try {
                $responses = Http::pool(function (Pool $pool) use ($pending, $key): void {
                    foreach ($pending as $id => $request) {
                        $body = $request['path'] === '/responses' ? $this->textOptions($request['body']) : $request['body'];
                        $pool->as((string) $id)->withToken($key)->acceptJson()->connectTimeout(15)->timeout(180)
                            ->post(rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/').$request['path'], array_merge($body, ['stream' => false]));
                    }
                }, null);
            } catch (Throwable $exception) {
                Log::warning('OpenAI request pool did not finish.', ['error' => $exception->getMessage()]);
                foreach ($pending as $id => $request) {
                    $results[$id] = new RuntimeException('OpenAI request did not finish. Retry this batch item.', 0, $exception);
                }
                break;
            }
            $retry = [];
            foreach ($responses as $id => $response) {
                try {
                    if ($response instanceof Throwable) {
                        throw $response;
                    }
                    $results[$id] = $this->responseData($response);
                    if ($pending[$id]['path'] === '/images/generations') {
                        AiRateLimit::succeeded();
                    }
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

    public function storeImage(array $response, string $folder, bool $badge = false): string
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
        if ($badge) {
            if ($extension !== 'png' || ! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
                throw new RuntimeException('A transparent PNG and GD are required for collection badges.');
            }
            $source = @imagecreatefromstring($bytes);
            $resized = imagecreatetruecolor(300, 450);
            if (! $source || ! $resized) {
                throw new RuntimeException('Could not resize the generated badge.');
            }
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
            imagecopyresampled($resized, $source, 0, 0, 0, 0, 300, 450, imagesx($source), imagesy($source));
            ob_start();
            imagepng($resized, null, 8);
            $bytes = ob_get_clean();
            imagedestroy($resized);
            imagedestroy($source);
        } elseif ($extension !== 'webp' || strlen($bytes) >= 299000 || ($details[0] ?? 0) !== 1200 || ($details[1] ?? 0) !== 800) {
            if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
                throw new RuntimeException('GD image processing is required to resize generated images.');
            }
            $source = @imagecreatefromstring($bytes);
            if (! $source) {
                throw new RuntimeException('The image model returned an unreadable image.');
            }
            try {
                try {
                    $bytes = $this->smallWebp($source);
                    $extension = 'webp';
                } catch (RuntimeException $exception) {
                    if ($extension !== 'webp' || strlen($bytes) >= 299000) {
                        throw $exception;
                    }
                }
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
        $resized = imagecreatetruecolor(1200, 800);
        if (! $resized) {
            throw new RuntimeException('Could not resize the generated image to 1200 × 800.');
        }
        try {
            if (! imagecopyresampled($resized, $source, 0, 0, 0, 0, 1200, 800, imagesx($source), imagesy($source))) {
                throw new RuntimeException('Could not resize the generated image to 1200 × 800.');
            }
            foreach ([85, 80, 75, 70, 65, 60, 55, 50, 45, 40, 35, 30, 25, 20, 15, 10] as $quality) {
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
        if (AiRateLimit::isTemporary($response)) {
            throw AiRateLimit::pause($response);
        }
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

        if (($data['status'] ?? '') === 'incomplete') {
            throw new RuntimeException(($data['incomplete_details']['reason'] ?? '') === 'max_output_tokens'
                ? 'The text model reached its token limit. Increase AI_TEXT_MAX_OUTPUT_TOKENS before retrying.'
                : 'The text model returned an incomplete response.');
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
