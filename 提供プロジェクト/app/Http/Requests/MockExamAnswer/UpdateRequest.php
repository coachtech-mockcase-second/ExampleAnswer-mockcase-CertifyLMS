<?php

declare(strict_types=1);

namespace App\Http\Requests\MockExamAnswer;

use App\Models\MockExamSession;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 受験中の解答 PATCH リクエスト(逐次保存)。
 *
 * `mock_exam_question_id` が session.generated_question_ids に含まれるか / `selected_option_id` が問題の選択肢か、
 * というドメイン整合性は UpdateAction 側で検証する(MockExamQuestionNotInSessionException / MockExamOptionMismatchException)。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof MockExamSession
            && ($this->user()?->can('saveAnswer', $session) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mock_exam_question_id' => ['required', 'ulid', 'exists:mock_exam_questions,id'],
            'selected_option_id' => ['required', 'ulid', 'exists:mock_exam_question_options,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mock_exam_question_id' => '問題',
            'selected_option_id' => '選択肢',
        ];
    }
}
