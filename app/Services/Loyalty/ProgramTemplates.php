<?php

namespace App\Services\Loyalty;

use App\Enums\BusinessType;
use App\Enums\StampIcon;
use App\Models\Business;

/**
 * Presetările din wizard, câte una per tip de afacere (SDD §7). Stau în cod, nu în DB (ADR 008):
 * sunt doar valori de pornire, programul creat nu mai depinde de ele.
 */
final class ProgramTemplates
{
    public static function for(BusinessType $type): ProgramTemplate
    {
        return match ($type) {
            BusinessType::Cafe => new ProgramTemplate('cafe', 'Cafenea', '5 cafele → 1 gratis, o ștampilă la 30 de minute', [
                'name' => '5 cafele = 1 gratis',
                'stamps_required' => 5,
                'reward_title' => 'Cafea gratis',
                'reward_description' => 'Orice cafea din meniu, la alegere.',
                'max_stamps_per_visit' => 1,
                'cooldown_minutes' => 30,
                'daily_limit' => 1,
                'stamps_expire_days' => null,
                'reward_expire_days' => 30,
                'rollover_extra' => false,
                'stamp_icon' => StampIcon::Coffee->value,
                'terms' => null,
            ]),
            BusinessType::Restaurant => new ProgramTemplate('restaurant', 'Restaurant', '8 mese → desert gratis', [
                'name' => '8 mese = desert gratis',
                'stamps_required' => 8,
                'reward_title' => 'Desert gratis',
                'reward_description' => 'Un desert din meniu, din partea casei.',
                'max_stamps_per_visit' => 1,
                'cooldown_minutes' => 60,
                'daily_limit' => 1,
                'stamps_expire_days' => null,
                'reward_expire_days' => 60,
                'rollover_extra' => false,
                'stamp_icon' => StampIcon::Cake->value,
                'terms' => null,
            ]),
            BusinessType::Carwash => new ProgramTemplate('carwash', 'Spălătorie auto', '10 spălări → 1 gratis, maximum una pe zi', [
                'name' => '10 spălări = 1 gratis',
                'stamps_required' => 10,
                'reward_title' => 'Spălare gratis',
                'reward_description' => 'O spălare standard, exterior și interior.',
                'max_stamps_per_visit' => 1,
                'cooldown_minutes' => 0,
                'daily_limit' => 1,
                'stamps_expire_days' => null,
                'reward_expire_days' => 90,
                'rollover_extra' => false,
                'stamp_icon' => StampIcon::Car->value,
                'terms' => null,
            ]),
            BusinessType::Other => new ProgramTemplate('other', 'Personalizat', '6 vizite → o recompensă aleasă de tine', [
                'name' => '6 vizite = 1 recompensă',
                'stamps_required' => 6,
                'reward_title' => 'Recompensă gratis',
                'reward_description' => null,
                'max_stamps_per_visit' => 1,
                'cooldown_minutes' => 30,
                'daily_limit' => 1,
                'stamps_expire_days' => null,
                'reward_expire_days' => null,
                'rollover_extra' => false,
                'stamp_icon' => StampIcon::Star->value,
                'terms' => null,
            ]),
        };
    }

    /**
     * @return list<ProgramTemplate>
     */
    public static function all(): array
    {
        return array_map(self::for(...), BusinessType::cases());
    }

    /**
     * Toate template-urile, cu cel al tipului afacerii primul (preselectat în wizard).
     *
     * @return list<ProgramTemplate>
     */
    public static function orderedFor(Business $business): array
    {
        $first = self::for($business->type);

        return [$first, ...array_values(array_filter(self::all(), fn (ProgramTemplate $template) => $template->key !== $first->key))];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (BusinessType $type) => $type->value, BusinessType::cases());
    }
}
