<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\UseCases\Plan\ArchiveAction;
use App\UseCases\Plan\PublishAction;
use App\UseCases\Plan\UnarchiveAction;
use Illuminate\Http\RedirectResponse;

/**
 * 受講プランの状態遷移（公開 / アーカイブ / 下書きへ戻す）を管理者操作で実行する Controller。
 * 各遷移は対応する Action へ委譲し、遷移元の状態でのみ実行できるよう Policy で認可する。
 */
class PlanStatusController extends Controller
{
    public function publish(Plan $plan, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを公開しました。');
    }

    public function archive(Plan $plan, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランをアーカイブしました。');
    }

    public function unarchive(Plan $plan, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを下書きへ戻しました。');
    }
}
