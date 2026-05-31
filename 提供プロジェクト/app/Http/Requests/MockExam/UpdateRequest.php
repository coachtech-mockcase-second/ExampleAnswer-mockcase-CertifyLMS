<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExam;

use App\Models\MockExam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 模試マスタの更新リクエスト。`certification_id` は不可変なので受け付けない。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $mockExam = $this->route('mockExam');

        return $mockExam instanceof MockExam
            && ($this->user()?->can('update', $mockExam) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'order' => ['required', 'integer', 'min:0', 'max:65535'],
            'passing_score' => ['required', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '模試名',
            'description' => '説明',
            'order' => '並び順',
            'passing_score' => '合格点(%)',
        ];
    }
}
