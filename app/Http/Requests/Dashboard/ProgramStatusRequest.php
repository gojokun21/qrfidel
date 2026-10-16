<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\ProgramStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgramStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ProgramStatus::class)->except([ProgramStatus::Draft])],
        ];
    }

    public function targetStatus(): ProgramStatus
    {
        return ProgramStatus::from((string) $this->validated('status'));
    }
}
