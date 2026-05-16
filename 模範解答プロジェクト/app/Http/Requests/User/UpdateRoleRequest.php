<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateRole', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'in:admin,coach,student'],
        ];
    }

    public function attributes(): array
    {
        return [
            'role' => 'ロール',
        ];
    }
}
