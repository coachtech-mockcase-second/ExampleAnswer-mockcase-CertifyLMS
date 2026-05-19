<?php

declare(strict_types=1);

namespace App\Http\Requests\SectionQuestion;

use App\Http\Controllers\SectionQuestionController;
use App\Models\Section;
use App\Models\SectionQuestion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Section 紐づき問題の新規作成リクエスト。
 *
 * options 配列は 2..6 件、各 option に body / is_correct / order を要求する。
 * is_correct=true がちょうど 1 件であることのドメイン整合性は StoreAction 側で検証する(QuestionInvalidOptionsException)。
 *
 * @see SectionQuestionController::store()
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            && ($this->user()?->can('create', [SectionQuestion::class, $section]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['required', 'ulid', 'exists:question_categories,id,deleted_at,NULL'],
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
