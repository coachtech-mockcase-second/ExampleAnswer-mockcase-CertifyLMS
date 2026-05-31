<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExamQuestion;

use App\Models\MockExamQuestion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 模試問題の更新リクエスト。`mock_exam_id` は不可変、選択肢は delete-and-insert で同期される。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question instanceof MockExamQuestion
            && ($this->user()?->can('update', $question) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'ulid', 'exists:question_categories,id'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.body' => ['required', 'string', 'max:1000'],
            'options.*.is_correct' => ['required', 'boolean'],
            'options.*.order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => '問題文',
            'explanation' => '解説',
            'category_id' => '出題分野',
            'options' => '選択肢',
            'options.*.body' => '選択肢本文',
            'options.*.is_correct' => '正答フラグ',
            'options.*.order' => '選択肢の並び順',
        ];
    }
}
