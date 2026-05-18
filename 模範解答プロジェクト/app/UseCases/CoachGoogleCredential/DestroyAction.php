<?php

declare(strict_types=1);

namespace App\UseCases\CoachGoogleCredential;

use App\Models\CoachGoogleCredential;
use App\Services\Google\GoogleOAuthService;

/**
 * コーチによる Google Calendar 連携解除ユースケース。
 *
 * Google 側で `refresh_token` を revoke した後、DB 行を SoftDelete する。revoke リクエスト失敗は
 * 履歴的に「LMS 側では切ったが Google 側に残った」状況になるため warning ログを残しつつも
 * SoftDelete は実行する(LMS 内の挙動を優先)。
 */
final class DestroyAction
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
    ) {}

    public function __invoke(CoachGoogleCredential $credential): void
    {
        $this->oauthService->revoke($credential->refresh_token);
        $credential->delete();
    }
}
