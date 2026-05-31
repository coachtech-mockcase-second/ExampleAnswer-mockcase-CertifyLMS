<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\SectionQuestion;
use App\UseCases\SectionQuiz\ShowAction;
use App\UseCases\SectionQuiz\ShowQuestionAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Section 紐づき問題演習 (教材 Section 詳細起点) の Web Controller。
 *
 * - show: Section エントリ画面 (問題カード一覧)
 * - showQuestion: 1 問出題画面
 */
class SectionQuizController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function show(Section $section, ShowAction $action): View
    {
        $this->ensureCanViewQuiz($section);

        $student = auth()->user();
        $section = $action($section, $student);

        return view('quiz.sections.show', [
            'section' => $section,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    public function showQuestion(
        Section $section,
        SectionQuestion $question,
        ShowQuestionAction $action,
    ): View {
        $this->ensureCanViewQuiz($section);

        $student = auth()->user();
        $data = $action($section, $question, $student);

        return view('quiz.sections.question', [
            'section' => $section,
            'question' => $data['question'],
            'nextId' => $data['next_id'],
            'attempt' => $data['attempt'],
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureCanViewQuiz(Section $section): void
    {
        if (Gate::denies('quiz.section.view', $section)) {
            abort(404);
        }
    }
}
