<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OrganisationSeeder;
use RuntimeException;
use Tests\TestCase;

class ProductionSeederSafetyTest extends TestCase
{
    public function test_demo_database_seeder_is_blocked_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Demo seeding is disabled in production');
            (new DatabaseSeeder())->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_demo_organisation_seeder_is_blocked_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Demo organisation seeding is disabled in production');
            (new OrganisationSeeder())->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }
}
