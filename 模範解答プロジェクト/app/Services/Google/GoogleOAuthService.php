<?php

declare(strict_types=1);

namespace App\Services\Google;

use Google\Client as GoogleClient;

/**
 * Google API クライアントを集中生成する Service。OAuth 認可 URL の発行と、共通設定を済ませた
 * `Google\Client` インスタンスを返す責務だけを持つ。
 *
 * Client ID / Secret は `config('services.google.*')` から `.env` 経由で読み込む。
 */
// final は外している(Mockery でテスト時にスタブ可能にするため、backend-services.md 「Mockery で
// テストする Service は final 不採用可」方針)
class GoogleOAuthService
{
    /**
     * 共通設定(client_id / client_secret / redirect_uri / scopes)を済ませた Google\Client を返す。
     * 呼出側で `access_token` をセットすれば API 呼び出しを開始できる。
     */
    public function buildClient(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->setScopes((array) config('services.google.scopes', []));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    /**
     * OAuth 認可 URL を生成する。`state` には callback 後の処理に必要な情報を JSON で詰める。
     *
     * @param  array<string, mixed>  $state  callback 時に検証する任意 payload(`coach_id` / `redirect_path` 等)
     */
    public function getAuthUrl(array $state): string
    {
        $client = $this->buildClient();
        $client->setState(json_encode($state, JSON_THROW_ON_ERROR));

        return $client->createAuthUrl();
    }

    /**
     * 認可 code を access_token / refresh_token に交換する。
     *
     * @return array{access_token: string, refresh_token?: string, expires_in?: int, scope?: string, token_type?: string, created?: int}
     *
     * @throws \Google\Service\Exception
     */
    public function exchangeCode(string $code): array
    {
        $client = $this->buildClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth code exchange failed: '.($token['error_description'] ?? $token['error']));
        }

        return $token;
    }

    /**
     * 既存の認証情報を Google 側で revoke する。revoke 失敗は Service 層では握りつぶし、
     * 呼出側で DB の SoftDelete を続行できるようにする(教材として失敗パスを明示)。
     */
    public function revoke(string $token): bool
    {
        $client = $this->buildClient();

        return (bool) $client->revokeToken($token);
    }
}
