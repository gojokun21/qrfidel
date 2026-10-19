<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\BusinessType;
use App\Models\User;
use App\Services\Loyalty\IdempotentCreation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessRequest extends FormRequest
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
        // O retrimitere a aceluiași formular nu e respinsă de propria afacere creată deja cu această cheie.
        $created = $this->user() instanceof User && is_string($key = $this->input('idempotency_key'))
            ? app(IdempotentCreation::class)->existing($this->user(), 'business', $key)
            : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('businesses', 'slug')->ignore($created)],
            'type' => ['required', Rule::enum(BusinessType::class)],
            'timezone' => ['required', 'timezone:all'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Adresa poate conține doar litere mici, cifre și cratime.',
            'slug.unique' => 'Adresa e deja folosită de altă afacere.',
            'brand_color.regex' => 'Culoarea trebuie să fie în formatul #rrggbb.',
        ];
    }
}
