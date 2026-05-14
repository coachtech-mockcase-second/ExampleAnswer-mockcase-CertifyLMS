<?php

namespace App\Http\Requests\CertificationCatalog;

use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'ulid', 'exists:certification_categories,id'],
            'difficulty' => ['nullable', 'in:beginner,intermediate,advanced,expert'],
            'tab' => ['nullable', 'in:catalog,enrolled'],
        ];
    }
}
