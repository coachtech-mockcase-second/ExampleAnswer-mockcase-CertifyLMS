<?php

declare(strict_types=1);

namespace App\UseCases\CoachGoogleCredential;

use App\Models\User;
use App\Services\Google\GoogleOAuthService;

/**
 * コーチが「Googleカレンダーと連携」ボタンを押した際の OAuth 認可 URL 取得ユースケース。
 *
 * `state` に `coach_id` と redirect 戻り先パスを JSON で詰めることで、callback 時に「誰の連携か」
 * 「どこへ戻すか」を再現可能にする(セッションに頼らず stateless にする)。
 */
final class FetchAuthUrlAction
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
    ) {}

    public function __invoke(User $coach, string $redirectPath = '/settings/profile'): string
    {
        return $this->oauthService->getAuthUrl([
            'coach_id' => $coach->id,
            'redirect_path' => $redirectPath,
        ]);
    }
}
