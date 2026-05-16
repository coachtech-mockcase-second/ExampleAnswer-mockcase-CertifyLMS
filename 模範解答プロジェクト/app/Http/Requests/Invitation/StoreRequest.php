<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invitation::class);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:coach,student'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'メールアドレス',
            'role' => 'ロール',
        ];
    }
}
