<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    /**
     * În rularea paralelă, Laravel șterge și recreează baza unui worker la orice eroare de conexiune
     * (de ex. porturi locale epuizate pe Windows), dar RefreshDatabase ține minte că a migrat deja.
     * Resetăm indicatorul ca testul următor să ruleze migrațiile pe baza nouă, goală.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        ParallelTesting::setUpTestDatabase(function () {
            RefreshDatabaseState::$migrated = false;
        });

        return $app;
    }
}
