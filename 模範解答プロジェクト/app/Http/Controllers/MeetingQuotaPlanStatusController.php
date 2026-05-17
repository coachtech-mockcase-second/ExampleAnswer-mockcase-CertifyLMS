<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MeetingQuotaPlan;
use App\UseCases\MeetingQuotaPlan\ArchiveAction;
use App\UseCases\MeetingQuotaPlan\PublishAction;
use App\UseCases\MeetingQuotaPlan\UnarchiveAction;
use Illuminate\Http\RedirectResponse;

/**
 * 追加面談 SKU マスタの状態遷移(draft ↔ published ↔ archived)を扱う admin Controller。
 * CRUD 本体は MeetingQuotaPlanController に分離している。
 */
class MeetingQuotaPlanStatusController extends Controller
{
    public function publish(MeetingQuotaPlan $plan, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-quota-plans.show', $plan)
            ->with('success', '追加面談プランを公開しました。');
    }

    public function archive(MeetingQuotaPlan $plan, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-quota-plans.show', $plan)
            ->with('success', '追加面談プランをアーカイブしました。');
    }

    public function unarchive(MeetingQuotaPlan $plan, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-quota-plans.show', $plan)
            ->with('success', '追加面談プランを下書きへ戻しました。');
    }
}
