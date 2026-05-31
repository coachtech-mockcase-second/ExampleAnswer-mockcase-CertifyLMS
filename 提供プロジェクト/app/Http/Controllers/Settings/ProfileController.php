<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateRequest;
use App\UseCases\Availability\IndexAction as AvailabilityIndexAction;
use App\UseCases\Profile\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 全ロールの「自分自身のプロフィール設定画面」のエントリポイント。
 *
 * `edit`: タブ切替(`?tab=profile|password|meeting`)で `settings.profile` ビューを返す。
 * `meeting` タブはコーチ限定で、Google カレンダー連携 + 面談可能時間枠カレンダーを集約描画する。
 * `update`: name / bio / コーチのみ meeting_url を更新し、`?tab=profile` 状態でリダイレクト。
 */
class ProfileController extends Controller
{
    public function edit(Request $request, AvailabilityIndexAction $availabilityIndex): View
    {
        $user = $request->user();
        $payload = ['user' => $user];

        if ($user?->role === UserRole::Coach) {
            // 面談設定タブのカレンダー描画に必要。プロフィール / パスワードタブでも描画は軽量のため
            // 常時 eager 読込してテンプレ側のタブ切替コストを下げる。
            $payload['availabilities'] = $availabilityIndex($user);
        }

        return view('settings.profile', $payload);
    }

    public function update(UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'profile'])
            ->with('success', 'プロフィールを更新しました。');
    }
}
