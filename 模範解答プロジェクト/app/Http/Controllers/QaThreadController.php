<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
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
 * 質問掲示板スレッド Controller。受講生 / コーチ / admin 共通で利用される。
 *
 * リソース固有認可は QaThreadPolicy に集約。投稿は student のみ、編集 / 解決マークは投稿者本人のみ
 * (admin 代行不可)。削除は投稿者本人または admin が可能で、投稿者削除時の「回答あり = 削除不可」状態ガードは
 * DestroyAction が QaThreadHasRepliesException (409) で担う(admin は無条件モデレーション削除)。
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

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => $filters,
            'certifications' => $this->certificationOptionsFor($request->user()),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $this->authorize('view', $thread);

        $thread = $action($thread);

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
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

        return view('qa-thread.edit', [
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

        $auth = auth()->user();
        $action($thread, $auth);

        $redirectRoute = $auth->role === UserRole::Admin
            ? 'admin.qa-board.index'
            : 'qa-board.index';

        return redirect()
            ->route($redirectRoute)
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
     * フィルタ / 投稿フォームで使う資格選択肢を返す (admin は全資格、それ以外は公開済のみ)。
     *
     * @return Collection<int, Certification>
     */
    private function certificationOptionsFor(?User $user): Collection
    {
        $query = Certification::query()->orderBy('name');

        if ($user?->role !== UserRole::Admin) {
            $query->where('status', CertificationStatus::Published);
        }

        return $query->get(['id', 'name', 'status']);
    }
}
