<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MockExamSessionStatus;
use App\Http\Requests\MockExamSession\IndexRequest;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Services\WeaknessAnalysisService;
use App\UseCases\MockExamSession\DestroyAction;
use App\UseCases\MockExamSession\IndexAction;
use App\UseCases\MockExamSession\ShowAction;
use App\UseCases\MockExamSession\StartAction;
use App\UseCases\MockExamSession\StoreAction;
use App\UseCases\MockExamSession\SubmitAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講生の模試受験セッション Controller。
 *
 * - index: 受験履歴(graded / canceled)
 * - store: 新規セッション作成(資格別動線 from MockExamCatalog 経由)
 * - show: NotStarted / InProgress / Graded / Canceled で Blade を分岐
 * - start: NotStarted → InProgress
 * - submit: InProgress → Submitted → Graded(同 transaction)
 * - destroy: NotStarted → Canceled
 */
class MockExamSessionController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();
        $pass = isset($validated['pass'])
            ? in_array($validated['pass'], ['1', 'true', true], true)
            : null;

        $sessions = $action(
            student: $request->user(),
            certificationId: $validated['certification_id'] ?? null,
            mockExamId: $validated['mock_exam_id'] ?? null,
            pass: $pass,
        );

        return view('mock-exam-sessions.index', [
            'sessions' => $sessions,
            'certificationId' => $validated['certification_id'] ?? '',
            'mockExamId' => $validated['mock_exam_id'] ?? '',
            'pass' => $validated['pass'] ?? '',
        ]);
    }

    public function store(Enrollment $enrollment, MockExam $mockExam, StoreAction $action): RedirectResponse
    {
        $this->authorize('take', $mockExam);

        $session = $action(request()->user(), $mockExam);

        return redirect()
            ->route('mock-exam-sessions.show', $session)
            ->with('success', '受験セッションを作成しました。「受験を開始する」を押すと開始します。');
    }

    public function show(MockExamSession $session, ShowAction $action, WeaknessAnalysisService $weaknessAnalysis): View
    {
        $this->authorize('view', $session);

        $session = $action($session);

        return match ($session->status) {
            MockExamSessionStatus::NotStarted => view('mock-exam-sessions.lobby', [
                'session' => $session,
            ]),
            MockExamSessionStatus::InProgress => view('mock-exam-sessions.take', [
                'session' => $session,
                'questions' => $this->loadQuestionsForTake($session),
                'answers' => $session->answers->keyBy('mock_exam_question_id'),
            ]),
            MockExamSessionStatus::Submitted, MockExamSessionStatus::Graded => view('mock-exam-sessions.result', [
                'session' => $session,
                'heatmap' => $weaknessAnalysis->getHeatmap($session),
                'passProbabilityBand' => $weaknessAnalysis->getPassProbabilityBand($session->enrollment),
                'answers' => $session->answers->keyBy('mock_exam_question_id'),
                'questions' => $this->loadQuestionsForResult($session),
            ]),
            MockExamSessionStatus::Canceled => view('mock-exam-sessions.canceled', [
                'session' => $session,
            ]),
        };
    }

    public function start(MockExamSession $session, StartAction $action): RedirectResponse
    {
        $this->authorize('start', $session);

        $action($session);

        return redirect()
            ->route('mock-exam-sessions.show', $session)
            ->with('success', '受験を開始しました。');
    }

    public function submit(MockExamSession $session, SubmitAction $action): RedirectResponse
    {
        $this->authorize('submit', $session);

        $action($session);

        return redirect()
            ->route('mock-exam-sessions.show', $session)
            ->with('success', '答案を提出しました。採点結果を確認してください。');
    }

    public function destroy(MockExamSession $session, DestroyAction $action): RedirectResponse
    {
        $this->authorize('cancel', $session);

        $action($session);

        return redirect()
            ->route('mock-exam-sessions.index')
            ->with('success', '受験セッションをキャンセルしました。');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\MockExamQuestion>
     */
    private function loadQuestionsForTake(MockExamSession $session)
    {
        return \App\Models\MockExamQuestion::query()
            ->whereIn('id', $session->generated_question_ids ?? [])
            ->with(['category', 'options' => fn ($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();
    }

    /**
     * 結果画面は SoftDelete された問題も withTrashed で取得し、削除済の表示は淡色で出す前提。
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\MockExamQuestion>
     */
    private function loadQuestionsForResult(MockExamSession $session)
    {
        return \App\Models\MockExamQuestion::withTrashed()
            ->whereIn('id', $session->generated_question_ids ?? [])
            ->with([
                'category',
                'options' => fn ($q) => $q->withTrashed()->orderBy('order'),
            ])
            ->orderBy('order')
            ->get();
    }
}
