<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\Role;
use App\Http\Requests\Dashboard\Concerns\ValidatesBusinessLocation;
use App\Support\Tenancy\CurrentBusiness;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    use ValidatesBusinessLocation;

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
            'email' => ['required', 'string', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                $business = app(CurrentBusiness::class)->get();

                if ($business !== null && is_string($value)
                    && $business->members()->whereRaw('lower(users.email) = ?', [Str::lower(trim($value))])->exists()) {
                    $fail('Această persoană face deja parte din echipă.');
                }
            }],
            'role' => ['required', Rule::enum(Role::class)],
            'location_id' => ['nullable', 'integer', $this->locationOfCurrentBusiness()],
        ];
    }
}
