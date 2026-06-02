<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\StoreRequest;
use App\Http\Requests\Availability\UpdateRequest;
use App\Models\CoachAvailability;
use App\UseCases\Availability\DestroyAction;
use App\UseCases\Availability\IndexAction;
use App\UseCases\Availability\StoreAction;
use App\UseCases\Availability\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * コーチが面談可能時間枠(`CoachAvailability`)を管理する専用画面 (`/settings/availability`)。
 *
 * `index` が面談設定ページ(Google カレンダー連携 + 週間カレンダー)を描画し、
 * `store` / `update` / `destroy` が枠の CRUD を受け持つ(Blade form の送信先)。
 * `role:coach` middleware で他ロールは 403。本人所有確認は `CoachAvailabilityPolicy` を経由する。
 */
class AvailabilityController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $user = $request->user();

        return view('settings.availability', [
            'user' => $user,
            'availabilities' => $action($user),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.availability.index')
            ->with('success', '面談可能時間枠を追加しました。');
    }

    public function update(
        CoachAvailability $availability,
        UpdateRequest $request,
        UpdateAction $action,
    ): RedirectResponse {
        $action($availability, $request->validated());

        return redirect()
            ->route('settings.availability.index')
            ->with('success', '面談可能時間枠を更新しました。');
    }

    public function destroy(
        Request $request,
        CoachAvailability $availability,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $availability);

        $action($availability);

        return redirect()
            ->route('settings.availability.index')
            ->with('success', '面談可能時間枠を削除しました。');
    }
}
