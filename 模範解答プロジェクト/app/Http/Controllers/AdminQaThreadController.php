<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Http\Requests\AdminQaThread\IndexRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\UseCases\AdminQaThread\DestroyAction;
use App\UseCases\AdminQaThread\IndexAction;
use App\UseCases\AdminQaThread\ShowAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 管理者向け質問掲示板モデレーション Controller。
 *
 * - index: 全資格 (draft / published / archived 問わず) のスレッドを一覧、`with_trashed` で SoftDelete 含む
 * - show: SoftDelete 済の回答も含めて表示 (モデレーション履歴の確認)
 * - destroy: 回答有無不問でスレッドを SoftDelete (履歴は維持)
 */
class AdminQaThreadController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $filters = $request->filters();
        $withTrashed = $request->withTrashed();

        $threads = $action($filters, $withTrashed);

        return view('admin.qa-board.index', [
            'threads' => $threads,
            'filters' => $filters,
            'withTrashed' => $withTrashed,
            'certifications' => Certification::query()
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function show(QaThread $thread, ShowAction $action): View
    {
        $thread = $action($thread, withTrashedReplies: true);

        return view('admin.qa-board.show', [
            'thread' => $thread,
        ]);
    }

    public function destroy(QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $action($thread);

        return redirect()
            ->route('admin.qa-board.index')
            ->with('success', 'スレッドを削除しました。');
    }
}
