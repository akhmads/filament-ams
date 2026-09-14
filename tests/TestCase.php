<?php

namespace Tests;

use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The settings memo is static and survives the whole PHPUnit process,
        // while the database is rolled back per test. Without this, values from
        // an earlier test would leak into the next.
        Setting::flush();
        AssetCategory::forgetTreePath();
        Location::forgetTreePath();
    }
}
