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
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $chapter = $action($part, $request->user(), $request->validated());

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapter を作成しました。');
    }

    public function update(Chapter $chapter, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($chapter, $request->user(), $request->validated());

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapter を更新しました。');
    }

    public function destroy(Chapter $chapter, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $chapter);

        $partId = $chapter->part_id;
        $action($chapter);

        return redirect()
            ->route('admin.parts.show', $partId)
            ->with('success', 'Chapter を削除しました。');
    }

    public function publish(Chapter $chapter, PublishAction $action, Request $request): RedirectResponse
    {
        $this->authorize('publish', $chapter);

        $action($chapter, $request->user());

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapter を公開しました。');
    }

    public function unpublish(Chapter $chapter, UnpublishAction $action, Request $request): RedirectResponse
    {
        $this->authorize('unpublish', $chapter);

        $action($chapter, $request->user());

        return redirect()
            ->route('admin.chapters.show', $chapter)
            ->with('success', 'Chapter を下書きに戻しました。');
    }

    public function reorder(Part $part, ReorderRequest $request, ReorderAction $action): RedirectResponse
    {
        $action($part, $request->user(), $request->validated()['ids']);

        return redirect()
            ->route('admin.parts.show', $part)
            ->with('success', '並び順を更新しました。');
    }
}
