<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AiStampGenerator
{
    public function sights(string $city, string $country): array
    {
        $response = $this->request('/responses', [
            'model' => config('services.openai.text_model'),
            'input' => "Identify exactly five distinct, real, notable visitor sights within {$city}, {$country}. Return only a JSON array of five short sight names. Do not invent attractions or choose sights outside this city. No markdown.",
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

    public function image(string $category, string $name, string $folder): string
    {
        $title = $category === 'Top Sight' ? '' : ($category === 'City' || $category === 'State' ? explode(', ', $name)[0] : $name);
        $titleInstruction = $title === ''
            ? 'Include no visible text, title panel, letters or numbers.'
            : 'At the top center print exactly "'.$title.'" in bold old-style serif lettering and no other text. Put a tiny centered diamond under the title with a short thin rule on each side.';
        $subject = match ($category) {
            'Country' => 'Depict defining real landmarks, architecture, landscape, and plants in a coherent panorama.',
            'State' => 'Depict defining real landmarks, architecture, landscape, and plants of this US state.',
            'City' => 'Show recognizable real landmarks in a geographically accurate city panorama.',
            default => 'Show this exact attraction as the central subject with accurate surroundings.',
        };
        $prompt = "Generate a complete vintage postage stamp for {$name} ({$category}). Warm aged cream paper and deep forest-green ink only. Use lighter and darker tones of that same green for depth. Draw a narrow dark-green margin outside a finely scalloped perforated paper edge. Leave a narrow, consistent cream strip between perforations and two thin close rounded rectangular border lines. Keep border thickness the same on all sides and do not crop perforations. Draw about 30–35 shallow scallops across each horizontal edge and 24–25 down each vertical edge, with softly rounded corners. {$titleInstruction} {$subject} Fine etched lines, dense cross-hatching, layered depth, subtle aged-paper grain. Accurate local geography. Landscape 3:2 composition. No collage, invented landmarks, bright colors, logo, or watermark. Draw the entire stamp; the app adds no border or text afterward.";
        $model = config('services.openai.image_model');
        $response = $this->request('/images/generations', [
            'model' => $model,
            'prompt' => $prompt,
            'size' => str_starts_with($model, 'gpt-image-2') ? '1200x800' : '1536x1024',
            'quality' => 'high',
        ]);
        $encoded = $response['data'][0]['b64_json'] ?? null;
        $sourceBytes = $encoded ? base64_decode($encoded, true) : false;
        if (! $sourceBytes || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw new RuntimeException('Image generation or PHP GD WebP support is unavailable.');
        }
        $source = @imagecreatefromstring($sourceBytes);
        if (! $source) {
            throw new RuntimeException('The image model returned an unreadable image.');
        }
        try {
            $bytes = $this->smallWebp($source);
        } finally {
            imagedestroy($source);
        }
        $directory = public_path('images/'.$folder);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the image directory.');
        }
        $filename = Str::uuid().'.webp';
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
        $key = config('services.openai.api_key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY is missing.');
        }
        $response = Http::withToken($key)->acceptJson()->timeout(180)->retry(3, 2000)
            ->post('https://api.openai.com/v1'.$path, $body);
        if (! $response->successful()) {
            throw new RuntimeException('OpenAI API returned HTTP '.$response->status().': '.Str::limit((string) ($response->json('error.message') ?? $response->body()), 300));
        }

        return $response->json();
    }
}
