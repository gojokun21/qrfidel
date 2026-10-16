<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anularea unei tranzacții (SDD §5.6): cheia de idempotency e generată la randarea formularului,
 * deci un dublu-submit face replay; motivul e opțional și ajunge în meta și în audit.
 */
class VoidTransactionRequest extends FormRequest
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
            'idempotency_key' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'idempotency_key' => 'cheia de idempotency',
            'reason' => 'motivul',
        ];
    }

    public function idempotencyKey(): string
    {
        return $this->string('idempotency_key')->toString();
    }

    public function reason(): ?string
    {
        $reason = trim($this->string('reason')->toString());

        return $reason === '' ? null : $reason;
    }
}
