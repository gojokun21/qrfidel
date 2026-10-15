<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Cu config cache, phpunit.xml e ignorat și RefreshDatabase ar goli baza de dezvoltare.
if (is_file(__DIR__.'/../bootstrap/cache/config.php')) {
    throw new RuntimeException('Config-ul e în cache. Rulează `php artisan config:clear` (sau `composer test`).');
}

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
