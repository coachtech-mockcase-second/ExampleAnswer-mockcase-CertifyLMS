<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\UseCases\Plan\ArchiveAction;
use App\UseCases\Plan\PublishAction;
use App\UseCases\Plan\UnarchiveAction;
use Illuminate\Http\RedirectResponse;

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
