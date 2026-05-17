<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUserUsing;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Laravel Fortify の設定 Provider。
 *
 * - ログイン / パスワード変更 / パスワードリセット / ユーザー登録の各 Fortify Action を本プロジェクトの実装クラスへ束ねる
 * - ログインビュー / パスワードリセット系ビューを Blade に紐付ける
 * - 認証通過判定は AuthenticateUserUsing Action に委譲(in_progress / graduated のみ通過、invited / withdrawn はステータス漏洩なしの共通エラー)
 * - パスワードリセットメール送信時の存在有無漏洩を防ぐため、失敗レスポンスを成功レスポンスにエイリアス
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // アカウント列挙攻撃の防止: 存在しない email でも「送信しました」と同一メッセージを返す。
        $this->app->singleton(
            FailedPasswordResetLinkRequestResponse::class,
            SuccessfulPasswordResetLinkRequestResponse::class,
        );
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', [
            'token' => $request->route('token'),
            'email' => $request->email,
        ]));

        // 認証通過判定は AuthenticateUserUsing Action に集約:
        // in_progress / graduated のみ認証通過、invited / withdrawn は「認証情報が正しくありません」共通エラーに統一。
        Fortify::authenticateUsing(fn (Request $request) => app(AuthenticateUserUsing::class)($request));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
