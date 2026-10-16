<?php

namespace App\Http\Requests\Staff;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * O acțiune de staff pe card după scanare: tichetul scanării și cheia de idempotency (câmp sau header
 * `Idempotency-Key`, SDD §5.2). Formularul o generează la randare, deci un dublu-submit trimite aceeași cheie.
 */
class CardActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function ticket(): string
    {
        return $this->string('ticket')->toString();
    }

    public function idempotencyKey(): string
    {
        return $this->string('idempotency_key')->toString();
    }
}
