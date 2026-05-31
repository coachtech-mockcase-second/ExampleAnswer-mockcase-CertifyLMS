<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\UseCases\WeakDrill\IndexAction;
use App\UseCases\WeakDrill\ShowCategoryAction;
use App\UseCases\WeakDrill\ShowQuestionAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * 苦手分野ドリル Web Controller。
 *
 * - index: カテゴリ一覧 (おすすめバッジ + 正答率)
 * - showCategory: カテゴリ別の SectionQuestion リスト
 * - showQuestion: ドリル経路の 1 問出題画面
 */
class WeakDrillController extends Controller
{
    public function index(Enrollment $enrollment, IndexAction $action): View
    {
        $this->ensureCanDrill($enrollment);

        $data = $action($enrollment);

        return view('quiz.drills.index', [
            'enrollment' => $enrollment,
            'categories' => $data['categories'],
            'statsById' => $data['statsById'],
            'weakCategoryIds' => $data['weakCategoryIds'],
        ]);
    }

    public function showCategory(
        Enrollment $enrollment,
        QuestionCategory $questionCategory,
        ShowCategoryAction $action,
    ): View {
        $this->ensureCanDrill($enrollment);

        $student = auth()->user();
        $questions = $action($enrollment, $questionCategory, $student);

        return view('quiz.drills.show', [
            'enrollment' => $enrollment,
            'category' => $questionCategory,
            'questions' => $questions,
        ]);
    }

    public function showQuestion(
        Enrollment $enrollment,
        QuestionCategory $questionCategory,
        SectionQuestion $question,
        ShowQuestionAction $action,
    ): View {
        $this->ensureCanDrill($enrollment);

        $student = auth()->user();
        $data = $action($enrollment, $questionCategory, $question, $student);

        return view('quiz.drills.question', [
            'enrollment' => $enrollment,
            'category' => $questionCategory,
            'question' => $data['question'],
            'nextId' => $data['next_id'],
            'attempt' => $data['attempt'],
        ]);
    }

    private function ensureCanDrill(Enrollment $enrollment): void
    {
        if (Gate::denies('quiz.weak-drill.view', $enrollment)) {
            abort(403);
        }
    }
}
