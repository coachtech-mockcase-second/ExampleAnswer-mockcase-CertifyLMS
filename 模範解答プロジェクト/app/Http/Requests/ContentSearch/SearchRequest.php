<?php

declare(strict_types=1);

namespace App\Http\Requests\ContentSearch;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'certification_id' => ['required', 'ulid'],
            'keyword' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return [
            'certification_id' => '資格 ID',
            'keyword' => 'キーワード',
        ];
    }
}
