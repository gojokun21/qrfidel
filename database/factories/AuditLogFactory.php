<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'action' => 'card.stamped',
            'payload' => ['quantity' => 1],
        ];
    }
}
