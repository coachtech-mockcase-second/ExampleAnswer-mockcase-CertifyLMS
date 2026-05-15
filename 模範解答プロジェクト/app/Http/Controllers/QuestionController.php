<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\QuestionDifficulty;
use App\Http\Requests\Question\IndexRequest;
use App\Http\Requests\Question\StoreRequest;
use App\Http\Requests\Question\UpdateRequest;
use App\Models\Certification;
use App\Models\Question;
use App\UseCases\Question\DestroyAction;
use App\UseCases\Question\IndexAction;
use App\UseCases\Question\PublishAction;
use App\UseCases\Question\ShowAction;
use App\UseCases\Question\StoreAction;
use App\UseCases\Question\UnpublishAction;
use App\UseCases\Question\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Certification $certification, IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $questions = $action(
            $certification,
            categoryId: $validated['category_id'] ?? null,
            difficulty: isset($validated['difficulty']) ? QuestionDifficulty::from($validated['difficulty']) : null,
            status: isset($validated['status']) ? ContentStatus::from($validated['status']) : null,
            standaloneOnly: filter_var($validated['standalone_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );

        return view('admin.contents.questions.index', [
            'certification' => $certification,
            'questions' => $questions,
            'categories' => $certification->questionCategories()->ordered()->get(),
            'filters' => $validated,
        ]);
    }

    public function create(Certification $certification): View
    {
        $this->authorize('create', [Question::class, $certification]);

        return view('admin.contents.questions.create', [
            'certification' => $certification,
            'categories' => $certification->questionCategories()->ordered()->get(),
        ]);
    }

    public function store(Certification $certification, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $question = $action($certification, $request->user(), $request->validated());

        return redirect()
            ->route('admin.questions.show', $question)
            ->with('success', '問題を作成しました。');
    }

    public function show(Question $question, ShowAction $action): View
    {
        $this->authorize('view', $question);

        return view('admin.contents.questions.show', [
            'question' => $action($question),
            'categories' => $question->certification->questionCategories()->ordered()->get(),
        ]);
    }

    public function update(Question $question, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($question, $request->user(), $request->validated());

        return redirect()
            ->route('admin.questions.show', $question)
            ->with('success', '問題を更新しました。');
    }

    public function destroy(Question $question, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $question);

        $certificationId = $question->certification_id;
        $action($question);

        return redirect()
            ->route('admin.certifications.questions.index', $certificationId)
            ->with('success', '問題を削除しました。');
    }

    public function publish(Question $question, PublishAction $action, Request $request): RedirectResponse
    {
        $this->authorize('publish', $question);

        $action($question, $request->user());

        return redirect()
            ->route('admin.questions.show', $question)
            ->with('success', '問題を公開しました。');
    }

    public function unpublish(Question $question, UnpublishAction $action, Request $request): RedirectResponse
    {
        $this->authorize('unpublish', $question);

        $action($question, $request->user());

        return redirect()
            ->route('admin.questions.show', $question)
            ->with('success', '問題を下書きに戻しました。');
    }
}
