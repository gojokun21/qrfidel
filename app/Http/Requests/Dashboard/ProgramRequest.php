<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\StampIcon;
use App\Services\Loyalty\ProgramTemplates;
use App\Support\Color;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Câmpurile wizard-ului de programe (SDD §7). Limitele de sus sunt de bun-simț, cele de jos repetă CHECK-urile din DB.
 */
class ProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => ['nullable', 'string', Rule::in(ProgramTemplates::keys())],
            // Doar la creare: după aceea statusul se schimbă din acțiunile programului.
            'intent' => [$this->isMethod('POST') ? 'required' : 'nullable', Rule::in(['draft', 'publish'])],

            'name' => ['required', 'string', 'max:120'],
            'stamps_required' => ['required', 'integer', 'min:1', 'max:50'],
            'max_stamps_per_visit' => ['required', 'integer', 'min:1', 'lte:stamps_required'],
            'cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:50', 'gte:max_stamps_per_visit'],
            'stamps_expire_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'reward_expire_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'rollover_extra' => ['required', 'boolean'],

            'reward_title' => ['required', 'string', 'max:120'],
            'reward_description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'regex:'.Color::HEX_PATTERN],
            'stamp_icon' => ['required', Rule::enum(StampIcon::class)],
            'terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'numele programului',
            'stamps_required' => 'ștampilele necesare',
            'max_stamps_per_visit' => 'ștampilele pe vizită',
            'cooldown_minutes' => 'pauza între ștampile',
            'daily_limit' => 'limita zilnică',
            'stamps_expire_days' => 'expirarea ștampilelor',
            'reward_expire_days' => 'expirarea recompensei',
            'reward_title' => 'recompensa',
            'reward_description' => 'descrierea recompensei',
            'color' => 'culoarea',
            'stamp_icon' => 'iconul ștampilei',
            'terms' => 'termenii',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_stamps_per_visit.lte' => 'Ștampilele pe vizită nu pot depăși ștampilele necesare.',
            'daily_limit.gte' => 'Limita zilnică trebuie să permită cel puțin o vizită completă.',
            'color.regex' => 'Culoarea trebuie să fie în formatul #RRGGBB.',
        ];
    }

    /**
     * Doar câmpurile programului, gata pentru `ProgramService`.
     *
     * @return array<string, mixed>
     */
    public function programData(): array
    {
        $data = $this->safe()->except(['template', 'intent']);
        $data['color'] = isset($data['color']) ? strtolower((string) $data['color']) : null;
        $data['rollover_extra'] = $this->boolean('rollover_extra');

        return $data;
    }
}
