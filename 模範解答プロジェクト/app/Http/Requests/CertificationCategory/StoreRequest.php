<?php

namespace App\Http\Requests\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CertificationCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:60', 'unique:certification_categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '分類名',
            'slug' => 'スラッグ',
            'sort_order' => '表示順',
        ];
    }
}
