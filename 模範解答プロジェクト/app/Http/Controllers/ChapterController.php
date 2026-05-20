<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Chapter\ReorderRequest;
use App\Http\Requests\Chapter\StoreRequest;
use App\Http\Requests\Chapter\UpdateRequest;
use App\Models\Chapter;
use App\Models\Part;
use App\UseCases\Chapter\DestroyAction;
use App\UseCases\Chapter\PublishAction;
use App\UseCases\Chapter\ReorderAction;
use App\UseCases\Chapter\ShowAction;
use App\UseCases\Chapter\StoreAction;
use App\UseCases\Chapter\UnpublishAction;
use App\UseCases\Chapter\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Chapter 管理 Controller。
 * Part 配下に紐付く CRUD + 状態遷移 + 並び替えを提供する。
 */
class ChapterController extends Controller
{
    public function show(Chapter $chapter, ShowAction $action): View
    {
        $this->authorize('view', $chapter);

        return view('admin.contents.chapters.show', [
            'chapter' => $action($chapter),
        ]);
    }

    public function store(Part $part, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $chapter = $action($part, $request->validated());

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapterを作成しました。');
    }

    public function update(Chapter $chapter, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($chapter, $request->validated());

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapterを更新しました。');
    }

    public function destroy(Chapter $chapter, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $chapter);

        $partId = $chapter->part_id;
        $action($chapter);

        return redirect()
            ->route('admin.parts.show', $partId)
            ->with('success', 'Chapterを削除しました。');
    }

    public function publish(Chapter $chapter, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $chapter);

        $action($chapter);

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapterを公開しました。');
    }

    public function unpublish(Chapter $chapter, UnpublishAction $action): RedirectResponse
    {
        $this->authorize('unpublish', $chapter);

        $action($chapter);

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapterを下書きに戻しました。');
    }

    public function reorder(Part $part, ReorderRequest $request, ReorderAction $action): RedirectResponse
    {
        $action($part, $request->validated()['ids']);

        return redirect()
            ->route('admin.parts.show', $part)
            ->with('success', '並び順を更新しました。');
    }
}
