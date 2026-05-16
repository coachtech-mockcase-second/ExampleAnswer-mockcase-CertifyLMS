<?php

declare(strict_types=1);

namespace App\Http\Requests\Certification;

use App\Models\Certification;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Certification::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'category_id' => ['nullable', 'ulid', 'exists:certification_categories,id'],
            'difficulty' => ['nullable', 'in:beginner,intermediate,advanced,expert'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
