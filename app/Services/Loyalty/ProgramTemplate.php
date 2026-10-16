<?php

namespace App\Services\Loyalty;

/**
 * O presetare din pasul 1 al wizard-ului: valori de pornire pentru un program, toate editabile.
 */
final readonly class ProgramTemplate
{
    /**
     * @param  array{name: string, stamps_required: int, reward_title: string, reward_description: string|null, max_stamps_per_visit: int, cooldown_minutes: int, daily_limit: int|null, stamps_expire_days: int|null, reward_expire_days: int|null, rollover_extra: bool, stamp_icon: string, terms: string|null}  $attributes
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $summary,
        public array $attributes,
    ) {}

    /**
     * @return array{key: string, label: string, summary: string, attributes: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'summary' => $this->summary,
            'attributes' => $this->attributes,
        ];
    }
}
