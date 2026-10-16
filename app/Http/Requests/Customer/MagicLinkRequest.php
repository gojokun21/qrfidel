<?php

namespace App\Http\Requests\Customer;

use App\Models\Business;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class MagicLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'business' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Scrie adresa ta de email.',
            'email.email' => 'Adresa de email nu pare corectă.',
            'email.max' => 'Adresa de email e prea lungă.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Customer::normalizeEmail($this->input('email'))]);
        }
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    /**
     * Afacerea de pe pagina căreia s-a cerut linkul. Un slug necunoscut e ignorat (clientul ajunge pe /me).
     */
    public function business(): ?Business
    {
        $slug = $this->input('business');

        return Business::findActiveBySlug(is_string($slug) ? $slug : null);
    }
}
