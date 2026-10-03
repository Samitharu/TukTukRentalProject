<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Unit tests also get RefreshDatabase: several "unit" tests here are really
// service classes (PricingService, AvailabilityService) that legitimately
// read/write real Eloquent models — that's what makes them useful tests of
// the actual query logic (overlap rules, tier lookups) rather than mocks
// of it.
uses(TestCase::class, RefreshDatabase::class)->in('Feature', '../Modules/*/tests/Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Unit', '../Modules/*/tests/Unit');
