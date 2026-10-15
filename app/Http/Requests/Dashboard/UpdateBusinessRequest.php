<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\BusinessType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fără `slug`: adresa publică e fixată la creare (ADR 006).
 */
class UpdateBusinessRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(BusinessType::class)],
            'timezone' => ['required', 'timezone:all'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'brand_color.regex' => 'Culoarea trebuie să fie în formatul #rrggbb.',
        ];
    }
}
