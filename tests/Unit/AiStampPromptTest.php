<?php

namespace Tests\Unit;

use App\Services\AiStampGenerator;
use PHPUnit\Framework\TestCase;

class AiStampPromptTest extends TestCase
{
    public function test_city_title_keeps_commas_in_city_name(): void
    {
        $prompt = (new AiStampGenerator)->imagePrompt('City', 'Washington, DC, United States');

        $this->assertStringContainsString('At the top center print exactly "Washington, DC" and no other words or numbers.', $prompt);
        $this->assertStringContainsString('roughly 12 pixels', $prompt);
        $this->assertStringContainsString('30-35 small shallow scallops', $prompt);
        $this->assertStringContainsString('Additional direction: none.', $prompt);
    }

    public function test_top_sight_has_no_title_panel_instruction(): void
    {
        $prompt = (new AiStampGenerator)->imagePrompt('Top Sight', 'Eiffel Tower, Paris, France');

        $this->assertStringContainsString('This is a top-sight stamp: include NO title, place name, city name,', $prompt);
        $this->assertStringContainsString('Do not leave an empty title panel.', $prompt);
        $this->assertStringContainsString('the city and country identify its location only.', $prompt);
        $this->assertStringNotContainsString('At the top center print exactly', $prompt);
    }

    public function test_stamp_palette_and_layout_match_the_supplied_prompt_for_every_category(): void
    {
        foreach (['Country', 'State', 'City', 'Top Sight'] as $category) {
            $prompt = (new AiStampGenerator)->imagePrompt($category, 'Example, Location', '  More engraving  ');
            $this->assertStringContainsString('deep forest-green ink', $prompt);
            $this->assertStringContainsString('Do not use red, brown, blue, purple, teal,', $prompt);
            $this->assertStringContainsString('At 1200x800', $prompt);
            $this->assertStringContainsString('top and bottom bands must not be taller than the side bands.', $prompt);
            $this->assertStringContainsString('Additional direction: More engraving.', $prompt);
        }
    }
}
