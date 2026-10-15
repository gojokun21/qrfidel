<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Fără WithoutModelEvents: evenimentele modelelor completează business_id, public_id și secretul cardurilor.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
