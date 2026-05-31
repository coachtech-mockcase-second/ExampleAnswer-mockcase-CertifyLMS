<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\UseCases\SectionQuiz\ShowResultAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Section 経路の解答結果画面 Controller。
 *
 * 解答 INSERT 直後の 302 redirect 先 (`quiz.sections.result`)。
 * 本人 / answer・question 整合 / cascade visibility を検証して結果ペインを描画する。
 */
class SectionQuizResultController extends Controller
{
    public function show(
        Section $section,
        SectionQuestion $question,
        SectionQuestionAnswer $answer,
        ShowResultAction $action,
    ): View {
        $this->authorize('view', $answer);

        if (Gate::denies('quiz.section.view', $section)) {
            abort(404);
        }

        $student = auth()->user();
        $data = $action($section, $question, $answer, $student);

        return view('quiz.sections.result', [
            'section' => $section,
            'question' => $data['question'],
            'answer' => $data['answer'],
            'correctOption' => $data['correct_option'],
            'attempt' => $data['attempt'],
            'nextId' => $data['next_id'],
        ]);
    }
}
