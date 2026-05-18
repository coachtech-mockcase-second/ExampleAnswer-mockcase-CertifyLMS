<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受講生 / コーチが ChatRoom 一覧を取得する際の入力検証。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role === UserRole::Student || $role === UserRole::Coach;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
