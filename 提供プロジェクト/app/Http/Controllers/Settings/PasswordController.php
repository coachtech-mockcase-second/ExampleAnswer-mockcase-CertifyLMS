<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 本人のパスワード変更エントリポイント(`PUT /settings/password`)。
 *
 * 現在のパスワード照合 + 新パスワード(`min:8 confirmed`)の検証は Fortify 公式パターンの
 * `App\Actions\Fortify\UpdateUserPassword::update()` に委譲する。検証失敗時は `validateWithBag`
 * が `ValidationException` を throw し、Laravel デフォルトの redirect back + error bag 描画に流れる。
 * 成功時は `settings.profile.edit?tab=password` へリダイレクトし `session('status')` に Fortify 標準の
 * `password-updated` を入れて `<x-flash>` 経由で表示する。
 */
class PasswordController extends Controller
{
    public function update(Request $request, UpdateUserPassword $action): RedirectResponse
    {
        $action->update(
            $request->user(),
            $request->only(['current_password', 'password', 'password_confirmation']),
        );

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'password'])
            ->with('status', 'password-updated');
    }
}
