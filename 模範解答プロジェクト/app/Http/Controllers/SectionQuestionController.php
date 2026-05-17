<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Http\Requests\SectionQuestion\IndexRequest;
use App\Http\Requests\SectionQuestion\StoreRequest;
use App\Http\Requests\SectionQuestion\UpdateRequest;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\UseCases\SectionQuestion\DestroyAction;
use App\UseCases\SectionQuestion\IndexAction;
use App\UseCases\SectionQuestion\PublishAction;
use App\UseCases\SectionQuestion\ShowAction;
use App\UseCases\SectionQuestion\StoreAction;
use App\UseCases\SectionQuestion\UnpublishAction;
use App\UseCases\SectionQuestion\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Section 紐づき問題(SectionQuestion)の管理 Controller。
 * Section 経由でのみ問題にアクセスし、独立した問題(certification 直下) は持たない。
 *
 * @see \App\Policies\SectionQuestionPolicy
 */
class SectionQuestionController extends Controller
{
    public function index(Section $section, IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();
        $section->loadMissing('chapter.part.certification');

        $questions = $action(
            $section,
            categoryId: $validated['category_id'] ?? null,
            status: isset($validated['status']) ? ContentStatus::from($validated['status']) : null,
        );

        return view('admin.contents.section-questions.index', [
            'section' => $section,
            'questions' => $questions,
            'categories' => $section->chapter->part->certification->questionCategories()->ordered()->get(),
            'filters' => $validated,
        ]);
    }

    public function create(Section $section): View
    {
        $this->authorize('create', [SectionQuestion::class, $section]);
        $section->loadMissing('chapter.part.certification');

        return view('admin.contents.section-questions.create', [
            'section' => $section,
            'categories' => $section->chapter->part->certification->questionCategories()->ordered()->get(),
        ]);
    }

    public function store(Section $section, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $question = $action($section, $request->validated());

        return redirect()
            ->route('admin.section-questions.show', $question)
            ->with('success', '演習問題を作成しました。');
    }

    public function show(SectionQuestion $sectionQuestion, ShowAction $action): View
    {
        $this->authorize('view', $sectionQuestion);

        $question = $action($sectionQuestion);

        return view('admin.contents.section-questions.show', [
            'question' => $question,
            'categories' => $question->section->chapter->part->certification
                ->questionCategories()->ordered()->get(),
        ]);
    }

    public function update(SectionQuestion $sectionQuestion, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($sectionQuestion, $request->validated());

        return redirect()
            ->route('admin.section-questions.show', $sectionQuestion)
            ->with('success', '演習問題を更新しました。');
    }

    public function destroy(SectionQuestion $sectionQuestion, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $sectionQuestion);

        $sectionId = $sectionQuestion->section_id;
        $action($sectionQuestion);

        return redirect()
            ->route('admin.sections.questions.index', $sectionId)
            ->with('success', '演習問題を削除しました。');
    }

    public function publish(SectionQuestion $sectionQuestion, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $sectionQuestion);

        $action($sectionQuestion);

        return redirect()
            ->route('admin.section-questions.show', $sectionQuestion)
            ->with('success', '演習問題を公開しました。');
    }

    public function unpublish(SectionQuestion $sectionQuestion, UnpublishAction $action): RedirectResponse
    {
        $this->authorize('unpublish', $sectionQuestion);

        $action($sectionQuestion);

        return redirect()
            ->route('admin.section-questions.show', $sectionQuestion)
            ->with('success', '演習問題を下書きに戻しました。');
    }
}
