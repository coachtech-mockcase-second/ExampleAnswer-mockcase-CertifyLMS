<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Section\PreviewRequest;
use App\Http\Requests\Section\ReorderRequest;
use App\Http\Requests\Section\StoreRequest;
use App\Http\Requests\Section\UpdateRequest;
use App\Models\Chapter;
use App\Models\Section;
use App\UseCases\Section\DestroyAction;
use App\UseCases\Section\PreviewAction;
use App\UseCases\Section\PublishAction;
use App\UseCases\Section\ReorderAction;
use App\UseCases\Section\ShowAction;
use App\UseCases\Section\StoreAction;
use App\UseCases\Section\UnpublishAction;
use App\UseCases\Section\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function show(Section $section, ShowAction $action): View
    {
        $this->authorize('view', $section);

        return view('admin.contents.sections.show', [
            'section' => $action($section),
        ]);
    }

    public function store(Chapter $chapter, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $section = $action($chapter, $request->user(), $request->validated());

        return redirect()
            ->route('admin.sections.show', $section)
            ->with('success', 'Section を作成しました。');
    }

    public function update(Section $section, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($section, $request->user(), $request->validated());

        return redirect()
            ->route('admin.sections.show', $section)
            ->with('success', 'Section を更新しました。');
    }

    public function destroy(Section $section, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $section);

        $chapterId = $section->chapter_id;
        $action($section);

        return redirect()
            ->route('admin.chapters.show', $chapterId)
            ->with('success', 'Section を削除しました。');
    }

    public function publish(Section $section, PublishAction $action, Request $request): RedirectResponse
    {
        $this->authorize('publish', $section);

        $action($section, $request->user());

        return redirect()
            ->route('admin.sections.show', $section)
            ->with('success', 'Section を公開しました。');
    }

    public function unpublish(Section $section, UnpublishAction $action, Request $request): RedirectResponse
    {
        $this->authorize('unpublish', $section);

        $action($section, $request->user());

        return redirect()
            ->route('admin.sections.show', $section)
            ->with('success', 'Section を下書きに戻しました。');
    }

    public function reorder(Chapter $chapter, ReorderRequest $request, ReorderAction $action): RedirectResponse
    {
        $action($chapter, $request->user(), $request->validated()['ids']);

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', '並び順を更新しました。');
    }

    public function preview(Section $section, PreviewRequest $request, PreviewAction $action): JsonResponse
    {
        $html = $action($section, $request->validated()['body']);

        return response()->json(['html' => $html]);
    }
}
