<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MeetingPack;
use App\UseCases\MeetingPack\ArchiveAction;
use App\UseCases\MeetingPack\PublishAction;
use App\UseCases\MeetingPack\UnarchiveAction;
use Illuminate\Http\RedirectResponse;

/**
 * 追加面談 SKU マスタの状態遷移(draft ↔ published ↔ archived)を扱う admin Controller。
 * CRUD 本体は MeetingPackController に分離している。
 */
class MeetingPackStatusController extends Controller
{
    public function publish(MeetingPack $plan, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを公開しました。');
    }

    public function archive(MeetingPack $plan, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックをアーカイブしました。');
    }

    public function unarchive(MeetingPack $plan, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $plan);

        $action($plan, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $plan)
            ->with('success', '面談パックを下書きへ戻しました。');
    }
}
