<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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
        $website = file_get_contents(resource_path('views/website/index.php'));
        $this->assertStringContainsString('Kroo is the ultimate travel app for explorers.', $website);
        $this->assertStringContainsString('assets/kroo-logo.png', $website);

        $this->get('/kroo-website')->assertStatus(200);
    }

    public function test_admin_normalizes_images_and_includes_location_filters(): void
    {
        $this->get('/admin')->assertStatus(200);
        $admin = file_get_contents(resource_path('views/admin/index.php'));

        $this->assertStringNotContainsString('id="cropCanvas"', $admin);
        $this->assertStringContainsString('async function normalizeImage(input)', $admin);
        $this->assertStringContainsString('canvas.width = 1200', $admin);
        $this->assertStringContainsString('async function renderStates()', $admin);
        $this->assertStringContainsString('function setTableFilter(value)', $admin);
        $this->assertStringContainsString('row.collectionKindId === filter', $admin);
        $this->assertStringContainsString('row.countryCode === filter', $admin);
        $this->assertStringContainsString('function submitCitySearch()', $admin);
        $this->assertStringContainsString('>Search</button>', $admin);
        $this->assertStringContainsString('<th>No.</th>', $admin);
        $this->assertStringContainsString('function changeCityPage(offset)', $admin);
        $this->assertStringContainsString("['population', 'latitude', 'longitude']", $admin);
        $this->assertStringContainsString("'state-entry'", $admin);
        $this->assertStringContainsString("state.tab === 'countries' ? ''", $admin);
        $this->assertStringContainsString('This cannot be undone.', $admin);
    }

    public function test_uploaded_public_images_can_be_served_through_laravel(): void
    {
        $directory = public_path('images/collection');
        File::ensureDirectoryExists($directory);
        $file = $directory.'/route-test.png';
        File::put($file, 'image-content');

        try {
            $response = $this->get('/images/collection/route-test.png')->assertOk();
            $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('cache-control'));
        } finally {
            File::delete($file);
        }
    }

    public function test_uploaded_city_images_can_be_served_through_laravel(): void
    {
        $directory = public_path('images/cities');
        File::ensureDirectoryExists($directory);
        $file = $directory.'/city-route-test.png';
        File::put($file, 'image-content');

        try {
            $this->get('/images/cities/city-route-test.png')->assertOk();
        } finally {
            File::delete($file);
        }
    }
}
