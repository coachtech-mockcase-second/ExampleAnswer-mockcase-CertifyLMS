<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin,coach,student'],
            'status' => ['nullable', 'in:invited,active,withdrawn'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'keyword' => '検索キーワード',
            'role' => 'ロール',
            'status' => 'ステータス',
        ];
    }
}
