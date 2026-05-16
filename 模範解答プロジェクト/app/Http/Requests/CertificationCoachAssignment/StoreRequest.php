<?php

declare(strict_types=1);

namespace App\Http\Requests\CertificationCoachAssignment;

use App\Models\CertificationCoachAssignment;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CertificationCoachAssignment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'coach_user_id' => ['required', 'ulid', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'coach_user_id' => '担当コーチ',
        ];
    }
}
