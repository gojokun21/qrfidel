<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\Role;
use App\Http\Requests\Dashboard\Concerns\ValidatesBusinessLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
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
            'role' => ['required', Rule::enum(Role::class)],
            'location_id' => ['nullable', 'integer', $this->locationOfCurrentBusiness()],
        ];
    }
}
