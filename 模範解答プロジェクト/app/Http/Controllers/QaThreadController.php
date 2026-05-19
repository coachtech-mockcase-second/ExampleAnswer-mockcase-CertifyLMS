<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Http\Requests\QaThread\IndexRequest;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\ShowAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * 受講生 / コーチ向け質問掲示板スレッド Controller。
 *
 * リソース固有認可は QaThreadPolicy に集約。投稿は student のみ、回答は coach も可能、編集 / 削除 / 解決マークは
 * 投稿者本人のみ (admin 代行不可)。admin モデレーションは AdminQaThreadController を使う。
 */
class QaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        if ($request->isUnassignedCertificationForCoach()) {
            throw new AccessDeniedHttpException('担当外の資格にはアクセスできません。');
        }

        $filters = $request->filters();
        $threads = $action($request->user(), $filters);

        return view('qa-board.index', [
            'threads' => $threads,
            'filters' => $filters,
            'certifications' => $this->certificationOptionsFor($request->user()),
        ]);
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        $thread = $action($thread);

        return view('qa-board.show', [
            'thread' => $thread,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-board.create', [
            'certifications' => $this->certificationOptionsFor(auth()->user()),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        /** @var array{certification_id: string, title: string, body: string} $validated */
        $validated = $request->validated();
        $thread = $action($request->user(), $validated);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-board.edit', [
            'thread' => $thread->load('certification'),
        ]);
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        /** @var array{title: string, body: string} $validated */
        $validated = $request->validated();
        $action($thread, $validated);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済にマークしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }

    /**
     * フィルタ / 投稿フォームで使う資格選択肢を返す (公開済資格のみ)。
     *
     * @return Collection<int, Certification>
     */
    private function certificationOptionsFor(?User $user): Collection
    {
        return Certification::query()
            ->where('status', CertificationStatus::Published)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
