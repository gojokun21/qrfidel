<?php

namespace Database\Seeders;

use App\Enums\BusinessType;
use App\Enums\ProgramStatus;
use App\Enums\RewardStatus;
use App\Enums\Role;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Business;
use App\Models\Card;
use App\Models\CardTransaction;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Program;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Date demo (SDD §10, §12): o cafenea și o spălătorie. Toate parolele sunt `password`.
 *
 * Soldurile cardurilor sunt scrise aici direct, coerent cu tranzacțiile; în aplicație le scrie doar StampService.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must not run in production.');
        }

        DB::transaction(function () {
            $this->user('Admin Platformă', 'admin@demo.test', platformAdmin: true);

            $cafe = Business::factory()->create([
                'name' => 'Cafenea Demo',
                'slug' => 'cafenea-demo',
                'type' => BusinessType::Cafe,
                'brand_color' => '#7c4a2d',
            ]);
            $cafeCenter = Location::factory()->for($cafe)->create(['name' => 'Centru', 'address' => 'Str. Principală 1']);
            Location::factory()->for($cafe)->create(['name' => 'Gară', 'address' => 'Bd. Gării 10']);

            $cafe->members()->attach($this->user('Owner Cafenea', 'owner@cafenea.test'), ['role' => Role::Owner]);
            $cafe->members()->attach($this->user('Manager Cafenea', 'manager@cafenea.test'), ['role' => Role::Manager, 'location_id' => $cafeCenter->id]);
            $cafeStaff = $this->user('Barista Demo', 'staff@cafenea.test');
            $cafe->members()->attach($cafeStaff, ['role' => Role::Staff, 'location_id' => $cafeCenter->id]);

            // Cooldown 0 ca fluxul manual din SDD §12 să poată da 5 ștampile la rând.
            $coffee = Program::factory()->for($cafe)->create([
                'name' => '5 cafele = 1 gratis',
                'stamps_required' => 5,
                'reward_title' => 'Cafea gratis',
                'reward_description' => 'Orice cafea din meniu, la alegere.',
                'cooldown_minutes' => 0,
                'daily_limit' => null,
                'status' => ProgramStatus::Active,
            ]);

            $carwash = Business::factory()->create([
                'name' => 'Spălătorie Demo',
                'slug' => 'spalatorie-demo',
                'type' => BusinessType::Carwash,
                'brand_color' => '#1d4ed8',
            ]);
            $bay = Location::factory()->for($carwash)->create(['name' => 'Spălătoria 1', 'address' => 'Șos. Industrială 5']);

            $carwash->members()->attach($this->user('Owner Spălătorie', 'owner@spalatorie.test'), ['role' => Role::Owner]);
            $carwashStaff = $this->user('Operator Spălătorie', 'staff@spalatorie.test');
            $carwash->members()->attach($carwashStaff, ['role' => Role::Staff, 'location_id' => $bay->id]);

            $washes = Program::factory()->for($carwash)->create([
                'name' => '10 spălări = 1 gratis',
                'stamps_required' => 10,
                'reward_title' => 'Spălare gratuită',
                'cooldown_minutes' => 60,
                'daily_limit' => 1,
                'status' => ProgramStatus::Active,
            ]);

            $ana = Customer::factory()->create(['name' => 'Ana Popescu', 'email' => 'ana@client.test']);
            $bogdan = Customer::factory()->create(['name' => 'Bogdan Ionescu', 'email' => 'bogdan@client.test']);

            $this->card($coffee, $ana, $cafeStaff, $cafeCenter, stamps: 3);
            $this->card($coffee, $bogdan, $cafeStaff, $cafeCenter, stamps: 5);
            $this->card($washes, $ana, $carwashStaff, $bay, stamps: 2);
        });
    }

    private function user(string $name, string $email, bool $platformAdmin = false): User
    {
        $factory = User::factory();

        return ($platformAdmin ? $factory->platformAdmin() : $factory)->create([
            'name' => $name,
            'email' => $email,
        ]);
    }

    /**
     * Card cu `stamps` ștampile, câte una pe zi; la fiecare `stamps_required` se emite o recompensă (fără rollover).
     */
    private function card(Program $program, Customer $customer, User $staff, Location $location, int $stamps): Card
    {
        $card = Card::create(['program_id' => $program->id, 'customer_id' => $customer->id]);

        for ($i = $stamps; $i >= 1; $i--) {
            $at = now()->subDays($i);

            (new CardTransaction)->forceFill([
                'card_id' => $card->id,
                'type' => TransactionType::Stamp,
                'quantity' => 1,
                'staff_user_id' => $staff->id,
                'location_id' => $location->id,
                'source' => TransactionSource::Scan,
                'idempotency_key' => (string) Str::ulid(),
                'created_at' => $at,
            ])->save();
        }

        $rewards = intdiv($stamps, $program->stamps_required);

        for ($i = 0; $i < $rewards; $i++) {
            Reward::create([
                'card_id' => $card->id,
                'status' => RewardStatus::Available,
                'issued_at' => now()->subDay(),
            ]);
        }

        $card->forceFill([
            'stamps_balance' => $stamps - $rewards * $program->stamps_required,
            'rewards_available' => $rewards,
            'last_stamp_at' => $stamps > 0 ? now()->subDay() : null,
        ])->save();

        return $card;
    }
}
