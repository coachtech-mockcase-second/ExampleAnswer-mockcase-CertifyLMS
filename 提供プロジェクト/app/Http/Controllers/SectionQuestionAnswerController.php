<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AnswerSource;
use App\Http\Requests\SectionQuestionAnswer\StoreRequest;
use App\Models\SectionQuestion;
use App\UseCases\SectionQuestionAnswer\StoreAction;
use Illuminate\Http\RedirectResponse;

/**
 * 受講生による SectionQuestion 解答送信 Controller。
 *
 * Section 経路 / 苦手分野ドリル経路の共通エンドポイント。`source` 値で結果画面のルートを分岐する。
 * 認可は StoreRequest::authorize() の `quiz.answer.create` Gate に委譲。
 */
class SectionQuestionAnswerController extends Controller
{
    public function store(
        SectionQuestion $question,
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $validated = $request->validated();
        $option = $question->options()->whereKey($validated['selected_option_id'])->firstOrFail();
        $source = AnswerSource::from($validated['source']);

        $result = $action($request->user(), $question, $option, $source);

        return match ($source) {
            AnswerSource::SectionQuiz => redirect()->route('quiz.sections.result', [
                'section' => $validated['section_id'],
                'question' => $question->id,
                'answer' => $result->answer->id,
            ]),
            AnswerSource::WeakDrill => redirect()->route('quiz.drills.result', [
                'enrollment' => $validated['enrollment_id'],
                'questionCategory' => $validated['question_category_id'],
                'question' => $question->id,
                'answer' => $result->answer->id,
            ]),
        };
    }
}
