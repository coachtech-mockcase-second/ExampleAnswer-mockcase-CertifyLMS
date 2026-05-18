<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QuizHistory\IndexRequest;
use App\Models\Enrollment;
use App\UseCases\QuizHistory\IndexAction;
use Illuminate\View\View;

/**
 * 受講生本人の解答履歴一覧 Controller。
 *
 * フィルタ: Section / Category / 正誤 / 出題経路。answered_at 降順 / 20 件ページネーション。
 */
class QuizHistoryController extends Controller
{
    public function index(Enrollment $enrollment, IndexRequest $request, IndexAction $action): View
    {
        $answers = $action($enrollment, $request->filters());

        return view('quiz.history.index', [
            'enrollment' => $enrollment,
            'answers' => $answers,
            'filters' => $request->filters(),
        ]);
    }
}
