<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $website = file_get_contents(public_path('kroo-website.html'));
        $this->assertStringContainsString('Kroo is the ultimate travel app for explorers.', $website);
        $this->assertStringContainsString('assets/kroo-logo.png', $website);
    }

    public function test_admin_includes_image_crop_controls(): void
    {
        $admin = file_get_contents(base_path('reference/server/admin.html'));

        $this->assertStringContainsString('id="cropCanvas"', $admin);
        $this->assertStringContainsString('function applyCrop()', $admin);
        $this->assertStringContainsString('croppedFiles.get(el)', $admin);
    }
}
