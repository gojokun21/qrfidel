<?php

namespace App\Http\Requests\Staff;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Limita `max_stamps_per_visit` e regulă de business: o verifică StampService, cu mesaj pentru angajat.
 */
class StampCardRequest extends CardActionRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
