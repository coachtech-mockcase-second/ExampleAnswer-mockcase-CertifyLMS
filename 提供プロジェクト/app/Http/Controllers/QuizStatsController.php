<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QuizStats\IndexRequest;
use App\Models\Enrollment;
use App\Services\SectionQuestionAttemptStatsService;
use App\UseCases\QuizStats\IndexAction;
use Illuminate\View\View;

/**
 * 受講生本人の SectionQuestion 累計サマリ一覧 Controller。
 *
 * SectionQuestionAttempt をフィルタ + ソートしてページネーション表示する。
 * 全体サマリ (累計試行回数 / 正答率 / 最終解答日) は SectionQuestionAttemptStatsService から取得して同画面に表示。
 */
class QuizStatsController extends Controller
{
    public function index(
        Enrollment $enrollment,
        IndexRequest $request,
        IndexAction $action,
        SectionQuestionAttemptStatsService $stats,
    ): View {
        $attempts = $action($enrollment, $request->filters());
        $summary = $stats->summarize($enrollment);

        return view('quiz.stats.index', [
            'enrollment' => $enrollment,
            'attempts' => $attempts,
            'filters' => $request->filters(),
            'summary' => $summary,
        ]);
    }
}
