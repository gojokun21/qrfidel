<?php

namespace App\Http\Requests\Admin;

use App\Models\Business;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Suspendarea / reactivarea unei afaceri de către adminul platformei (ADR 014). Motivul e opțional și ajunge în audit.
 * Acțiunea e idempotentă pe stare, deci nu cere idempotency key.
 */
class ChangeBusinessStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->route('business');
        $ability = $this->routeIs('admin.businesses.reactivate') ? 'reactivate' : 'suspend';

        return $business instanceof Business && $this->user()?->can($ability, $business) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => 'motivul'];
    }

    public function reason(): ?string
    {
        $reason = trim($this->string('reason')->toString());

        return $reason === '' ? null : $reason;
    }
}
