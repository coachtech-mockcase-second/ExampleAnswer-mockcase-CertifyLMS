<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateRequest;
use App\UseCases\Profile\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 全ロールの「自分自身のプロフィール設定画面」のエントリポイント。
 *
 * `edit`: タブ切替(`?tab=profile|password`)で `settings.profile` ビューを返す。
 * `update`: name / bio / コーチのみ meeting_url を更新し、`?tab=profile` 状態でリダイレクト。
 */
class ProfileController extends Controller
{
    /**
     * 本人のプロフィール設定画面を、`?tab=profile|password` のタブ切替状態で表示する。
     */
    public function edit(Request $request): View
    {
        return view('settings.profile', ['user' => $request->user()]);
    }

    /**
     * 本人の name / bio（コーチは meeting_url も）を更新し、プロフィールタブへ戻す。
     */
    public function update(UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'profile'])
            ->with('success', 'プロフィールを更新しました。');
    }
}
