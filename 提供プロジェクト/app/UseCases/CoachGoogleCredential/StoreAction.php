<?php

declare(strict_types=1);

namespace App\UseCases\CoachGoogleCredential;

use App\Exceptions\Mentoring\GoogleOAuthException;
use App\Models\CoachGoogleCredential;
use App\Models\User;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Support\Facades\DB;

/**
 * Google OAuth callback で受け取った `code` を access_token / refresh_token に交換して保存する。
 *
 * `state` に詰められた `coach_id` が認証中のユーザーと一致するかを必ず検証し、
 * 不一致なら GoogleOAuthException を throw する(他人の連携を奪うリスクを防ぐ)。
 *
 * 既存の Credential がある場合は upsert で更新する(再連携シナリオ対応)。
 */
final class StoreAction
{
    public function __construct(
        private readonly GoogleOAuthService $oauthService,
    ) {}

    /**
     * @param array{coach_id?: string, redirect_path?: string} $state callback 時に渡される decoded state
     *
     * @throws GoogleOAuthException
     */
    public function __invoke(User $authUser, string $code, array $state): CoachGoogleCredential
    {
        $coachId = $state['coach_id'] ?? null;
        if ($coachId === null || $coachId !== $authUser->id) {
            throw GoogleOAuthException::stateMismatch();
        }

        $token = $this->oauthService->exchangeCode($code);
        if (! isset($token['refresh_token'])) {
            // refresh_token は初回連携時のみ返る。再連携時に欠落するケースは prompt=consent で防げる前提
            throw GoogleOAuthException::missingRefreshToken();
        }

        return DB::transaction(function () use ($authUser, $token) {
            $credential = CoachGoogleCredential::query()
                ->where('coach_id', $authUser->id)
                ->first();

            $payload = [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'calendar_id' => 'primary',
                'connected_at' => now(),
            ];

            if ($credential === null) {
                return CoachGoogleCredential::create([
                    'coach_id' => $authUser->id,
                    ...$payload,
                ]);
            }

            $credential->update($payload);

            return $credential->fresh();
        });
    }
}
