<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Part\ReorderRequest;
use App\Http\Requests\Part\StoreRequest;
use App\Http\Requests\Part\UpdateRequest;
use App\Models\Certification;
use App\Models\Part;
use App\UseCases\Part\DestroyAction;
use App\UseCases\Part\IndexAction;
use App\UseCases\Part\PublishAction;
use App\UseCases\Part\ReorderAction;
use App\UseCases\Part\ShowAction;
use App\UseCases\Part\StoreAction;
use App\UseCases\Part\UnpublishAction;
use App\UseCases\Part\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Part 管理 Controller。
 * 教材階層の最上位として資格マスタ配下に紐付く CRUD + 状態遷移 + 並び替えを提供する。
 */
class PartController extends Controller
{
    public function index(Certification $certification, IndexAction $action): View
    {
        $this->authorize('viewAny', [Part::class, $certification]);

        return view('admin.contents.parts.index', [
            'certification' => $certification,
            'parts' => $action($certification),
        ]);
    }

    public function show(Part $part, ShowAction $action): View
    {
        $this->authorize('view', $part);

        return view('admin.contents.parts.show', [
            'part' => $action($part),
        ]);
    }

    public function store(Certification $certification, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $part = $action($certification, $request->validated());

        return redirect()
            ->route('admin.parts.show', $part)
            ->with('success', 'Partを作成しました。');
    }

    public function update(Part $part, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($part, $request->validated());

        return redirect()
            ->route('admin.parts.show', $part)
            ->with('success', 'Partを更新しました。');
    }

    public function destroy(Part $part, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $part);

        $action($part);

        return redirect()
            ->route('admin.certifications.parts.index', $part->certification_id)
            ->with('success', 'Partを削除しました。');
    }

    public function publish(Part $part, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $part);

        $action($part);

        return redirect()
            ->route('admin.parts.show', $part)
            ->with('success', 'Partを公開しました。');
    }

    public function unpublish(Part $part, UnpublishAction $action): RedirectResponse
    {
        $this->authorize('unpublish', $part);

        $action($part);

        return redirect()
            ->route('admin.parts.show', $part)
            ->with('success', 'Partを下書きに戻しました。');
    }

    public function reorder(Certification $certification, ReorderRequest $request, ReorderAction $action): RedirectResponse
    {
        $action($certification, $request->validated()['ids']);

        return redirect()
            ->route('admin.certifications.parts.index', $certification)
            ->with('success', '並び順を更新しました。');
    }
}
