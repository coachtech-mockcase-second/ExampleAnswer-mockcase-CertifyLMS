<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExam;

use App\Models\MockExam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * admin / coach 用の模試マスタ一覧フィルタ。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MockExam::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'is_published' => ['nullable', 'in:0,1,true,false'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'certification_id' => '資格',
            'is_published' => '公開状態',
        ];
    }
}
