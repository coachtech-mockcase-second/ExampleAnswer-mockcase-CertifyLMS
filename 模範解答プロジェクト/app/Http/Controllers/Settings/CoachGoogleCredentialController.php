<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Exceptions\Mentoring\GoogleOAuthException;
use App\Http\Controllers\Controller;
use App\UseCases\CoachGoogleCredential\DestroyAction;
use App\UseCases\CoachGoogleCredential\FetchAuthUrlAction;
use App\UseCases\CoachGoogleCredential\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * コーチによる Google Calendar 連携 / 連携解除の HTTP エントリポイント。
 *
 * - `redirect`: 認可 URL を組み立てて Google へ 302 redirect する(認可 URL の JSON 返却はしない、教材として redirect の体感を優先)
 * - `callback`: Google からの callback を受けて `code` をトークンに交換し、`state.redirect_path` に戻す
 * - `destroy`: 既存連携を revoke + SoftDelete
 *
 * 連携状態の確認 UI 自体は `/settings/availability` の面談設定タブが所有しており、
 * 本 Controller には独立した `index` 画面を持たない。
 */
class CoachGoogleCredentialController extends Controller
{
    public function redirect(FetchAuthUrlAction $action): RedirectResponse
    {
        $redirectPath = request()->query('redirect_path', '/settings/availability');
        $url = $action(request()->user(), is_string($redirectPath) ? $redirectPath : '/settings/availability');

        return redirect()->away($url);
    }

    public function callback(Request $request, StoreAction $action): RedirectResponse
    {
        $code = (string) $request->query('code', '');
        $rawState = (string) $request->query('state', '');
        if ($code === '' || $rawState === '') {
            throw GoogleOAuthException::stateMismatch();
        }

        try {
            $state = json_decode($rawState, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw GoogleOAuthException::stateMismatch();
        }
        if (! is_array($state)) {
            throw GoogleOAuthException::stateMismatch();
        }

        $action($request->user(), $code, $state);

        $redirectPath = is_string($state['redirect_path'] ?? null) ? $state['redirect_path'] : '/settings/availability';

        return redirect($redirectPath)->with('success', 'Googleカレンダーと連携しました。');
    }

    public function destroy(Request $request, DestroyAction $action): RedirectResponse
    {
        $coach = $request->user();
        $credential = $coach?->googleCredential;

        if ($credential !== null) {
            $action($credential);
        }

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Googleカレンダー連携を解除しました。');
    }
}
