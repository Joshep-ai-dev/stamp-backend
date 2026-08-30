<?php

namespace Tests\Unit;

use App\Services\CountryResolver;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_cabo_verde_is_the_canonical_country_name(): void
    {
        $resolver = new CountryResolver;

        $this->assertSame('Cabo Verde', $resolver->resolve('CV')['name']);
        $this->assertSame('Cabo Verde', $resolver->resolve('Cape Verde')['name']);
    }
}
