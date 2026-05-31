<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MockExamQuestion\StoreRequest;
use App\Http\Requests\MockExamQuestion\UpdateRequest;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\UseCases\MockExamQuestion\DestroyAction;
use App\UseCases\MockExamQuestion\StoreAction;
use App\UseCases\MockExamQuestion\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 模試問題(模試マスタの子リソース)を直接 CRUD する Controller。
 *
 * shallow ルートで `index` / `create` / `store` は親 MockExam を受け、`show` / `edit` / `update` / `destroy` は MockExamQuestion を受ける。
 */
class MockExamQuestionController extends Controller
{
    public function index(MockExam $mockExam): View
    {
        $this->authorize('viewAny', [MockExamQuestion::class, $mockExam]);

        $questions = $mockExam
            ->mockExamQuestions()
            ->with(['category', 'options' => fn ($q) => $q->orderBy('order')])
            ->orderBy('order')
            ->get();

        return view('mock-exam-question.management.index', [
            'mockExam' => $mockExam->load('certification'),
            'questions' => $questions,
        ]);
    }

    public function create(MockExam $mockExam): View
    {
        $this->authorize('create', [MockExamQuestion::class, $mockExam]);

        $categories = $mockExam->certification->questionCategories()->ordered()->get();

        return view('mock-exam-question.management.create', [
            'mockExam' => $mockExam->load('certification'),
            'categories' => $categories,
        ]);
    }

    public function store(MockExam $mockExam, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($mockExam, $request->validated());

        return redirect()
            ->route('admin.mock-exams.questions.index', $mockExam)
            ->with('success', '問題を追加しました。');
    }

    public function show(MockExamQuestion $question): View
    {
        $this->authorize('view', $question);

        return view('mock-exam-question.management.show', [
            'mockExam' => $question->mockExam,
            'question' => $question->load(['category', 'options' => fn ($q) => $q->orderBy('order')]),
        ]);
    }

    public function edit(MockExamQuestion $question): View
    {
        $this->authorize('update', $question);

        $categories = $question->mockExam->certification->questionCategories()->ordered()->get();

        return view('mock-exam-question.management.edit', [
            'mockExam' => $question->mockExam,
            'question' => $question->load(['category', 'options' => fn ($q) => $q->orderBy('order')]),
            'categories' => $categories,
        ]);
    }

    public function update(MockExamQuestion $question, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($question, $request->validated());

        return redirect()
            ->route('admin.mock-exams.questions.index', $question->mockExam)
            ->with('success', '問題を更新しました。');
    }

    public function destroy(MockExamQuestion $question, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $question);

        $mockExam = $question->mockExam;
        $action($question);

        return redirect()
            ->route('admin.mock-exams.questions.index', $mockExam)
            ->with('success', '問題を削除しました。');
    }
}
