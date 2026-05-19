<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\StoreRequest;
use App\Http\Requests\Availability\UpdateRequest;
use App\Models\CoachAvailability;
use App\UseCases\Availability\DestroyAction;
use App\UseCases\Availability\StoreAction;
use App\UseCases\Availability\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * コーチが面談可能時間枠(`CoachAvailability`)を CRUD するエントリポイント。
 *
 * 編集 UI 本体は `/settings/profile?tab=meeting` の面談設定タブが所有しており、
 * 本 Controller は **POST / PATCH / DELETE のみを受け持つ API 的エンドポイント**(本 Feature の Blade form の送信先)。
 * `GET /settings/availability` (`index`) は旧設計の単独画面の名残りとして残し、プロフィール設定画面へ 302 redirect する。
 * `role:coach` middleware で他ロールは 403。本人所有確認は `CoachAvailabilityPolicy` を経由する。
 */
class AvailabilityController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.profile.edit', ['tab' => 'meeting']);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'meeting'])
            ->with('success', '面談可能時間枠を追加しました。');
    }

    public function update(
        CoachAvailability $availability,
        UpdateRequest $request,
        UpdateAction $action,
    ): RedirectResponse {
        $action($availability, $request->validated());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'meeting'])
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
            ->route('settings.profile.edit', ['tab' => 'meeting'])
            ->with('success', '面談可能時間枠を削除しました。');
    }
}
