<?php

declare(strict_types=1);

namespace App\Http\Requests\SectionQuestion;

use App\Http\Controllers\SectionQuestionController;
use App\Models\SectionQuestion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Section 紐づき問題の更新リクエスト。section_id は不可変、options が含まれる場合は delete-and-insert 同期。
 *
 * @see SectionQuestionController::update()
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('sectionQuestion');

        return $question instanceof SectionQuestion
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
            'options' => ['sometimes', 'required', 'array', 'min:2', 'max:6'],
            'options.*.body' => ['required_with:options', 'string', 'max:1000'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
            'options.*.order' => ['required_with:options', 'integer', 'min:0'],
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
