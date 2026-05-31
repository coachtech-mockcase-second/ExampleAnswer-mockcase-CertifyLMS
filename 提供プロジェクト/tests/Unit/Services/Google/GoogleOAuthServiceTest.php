<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Google;

use App\Services\Google\GoogleOAuthService;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Google OAuth 認可フロー Service `GoogleOAuthService` の単体テスト。
 * 認可 URL 発行(state の JSON 化)/ 認可コード交換(成功 + エラー応答時の例外)/ トークン取消(成功・失敗の bool 返却)を、
 * `buildClient()` を partial mock でスタブした `Google\Client` 上で検証する。
 *
 * モック手法に Mockery を採用する理由: `google/apiclient` は独自 HTTP クライアントを内部構築するため
 * `Http::fake` が効かない。`Http::preventStrayRequests()` で未モックの実通信を遮断し、ライブラリ更新で
 * 壊れやすいことを示すため `#[Group('external')]` で分離実行可能にする。
 */
#[Group('external')]
class GoogleOAuthServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 未モックの外部通信が発生したらテストを失敗させる最終ライン(API キー漏洩 / レート制限消費の防止)
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * `buildClient()` をスタブした GoogleOAuthService の partial mock を返す。
     * 実際の `getAuthUrl` / `exchangeCode` / `revoke` ロジックは本物を呼びつつ、内部の `Google\Client` だけ差し替える。
     */
    private function serviceWithClient(MockInterface $client): GoogleOAuthService
    {
        /** @var GoogleOAuthService&MockInterface $service */
        $service = Mockery::mock(GoogleOAuthService::class)->makePartial();
        $service->shouldReceive('buildClient')->andReturn($client);

        return $service;
    }

    public function test_get_auth_url_encodes_state_as_json_and_returns_url(): void
    {
        // Arrange
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('setState')
            ->once()
            ->with(json_encode(['coach_id' => 'coach-123', 'redirect_path' => '/settings/profile']));
        $client->shouldReceive('createAuthUrl')
            ->once()
            ->andReturn('https://accounts.google.com/o/oauth2/auth?state=...');
        $service = $this->serviceWithClient($client);

        // Act
        $url = $service->getAuthUrl(['coach_id' => 'coach-123', 'redirect_path' => '/settings/profile']);

        // Assert
        $this->assertSame('https://accounts.google.com/o/oauth2/auth?state=...', $url);
    }

    public function test_exchange_code_returns_token_array_on_success(): void
    {
        // Arrange
        $token = [
            'access_token' => 'ya29.test_access',
            'refresh_token' => '1//test_refresh',
            'expires_in' => 3599,
        ];
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('auth-code-xyz')
            ->andReturn($token);
        $service = $this->serviceWithClient($client);

        // Act
        $result = $service->exchangeCode('auth-code-xyz');

        // Assert
        $this->assertSame($token, $result);
        $this->assertSame('ya29.test_access', $result['access_token']);
    }

    public function test_exchange_code_throws_when_token_response_contains_error(): void
    {
        // Arrange
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->andReturn([
                'error' => 'invalid_grant',
                'error_description' => 'Bad Request',
            ]);
        $service = $this->serviceWithClient($client);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Google OAuth code exchange failed: Bad Request');
        $service->exchangeCode('expired-code');
    }

    public function test_revoke_returns_true_when_google_accepts_revocation(): void
    {
        // Arrange
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('revokeToken')
            ->once()
            ->with('ya29.some_token')
            ->andReturnTrue();
        $service = $this->serviceWithClient($client);

        // Act
        $revoked = $service->revoke('ya29.some_token');

        // Assert
        $this->assertTrue($revoked, 'Google が revoke を受理したら true を返すはず');
    }

    public function test_revoke_returns_false_when_google_rejects_revocation(): void
    {
        // Arrange
        // revoke 失敗は Service 層では握りつぶし、呼出側が DB 物理削除を続行できるよう false を返す
        $client = Mockery::mock(GoogleClient::class);
        $client->shouldReceive('revokeToken')->once()->andReturnFalse();
        $service = $this->serviceWithClient($client);

        // Act
        $revoked = $service->revoke('already-invalid-token');

        // Assert
        $this->assertFalse($revoked, 'Google が revoke を拒否したら false を返すはず');
    }
}
