<?php

declare(strict_types=1);

namespace App\Http\Requests\CertificationCategory;

use App\Models\CertificationCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 資格分類マスタ新規作成リクエスト。分類名・スラッグ・表示順を受け取る。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CertificationCategory::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:60', 'unique:certification_categories,slug'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '分類名',
            'slug' => 'スラッグ',
            'sort_order' => '表示順',
        ];
    }
}
