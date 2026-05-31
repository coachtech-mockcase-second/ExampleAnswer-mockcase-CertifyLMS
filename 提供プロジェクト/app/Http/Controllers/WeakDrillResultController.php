<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\UseCases\WeakDrill\ShowResultAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * 苦手分野ドリル経路の解答結果画面 Controller。
 *
 * Enrollment / Category / Question / Answer の整合を検証し、ドリル経路用の結果ペインを描画する。
 */
class WeakDrillResultController extends Controller
{
    public function show(
        Enrollment $enrollment,
        QuestionCategory $questionCategory,
        SectionQuestion $question,
        SectionQuestionAnswer $answer,
        ShowResultAction $action,
    ): View {
        $this->authorize('view', $answer);

        if (Gate::denies('quiz.weak-drill.view', $enrollment)) {
            abort(403);
        }

        $student = auth()->user();
        $data = $action($enrollment, $questionCategory, $question, $answer, $student);

        return view('quiz.drills.result', [
            'enrollment' => $enrollment,
            'category' => $questionCategory,
            'question' => $data['question'],
            'answer' => $data['answer'],
            'correctOption' => $data['correct_option'],
            'attempt' => $data['attempt'],
            'nextId' => $data['next_id'],
        ]);
    }
}
