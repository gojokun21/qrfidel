<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use App\Services\Loyalty\StaffLocationService;
use App\Support\Tenancy\CurrentBusiness;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Locația aleasă trebuie să fie una în care angajatul poate scana (a afacerii curente; cea fixă, dacă are una).
 */
class ChooseLocationRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();
        $business = app(CurrentBusiness::class)->get() ?? abort(403);

        return [
            'location_id' => ['required', 'integer', Rule::in(app(StaffLocationService::class)->selectable($user, $business)->modelKeys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.in' => 'Nu poți scana în această locație.',
        ];
    }
}
