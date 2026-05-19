<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_KEY = 'test-analytics-api-key-32-chars-aaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['analytics-export.api_key' => self::VALID_KEY]);
    }

    public function test_request_with_matching_key_passes(): void
    {
        $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
            ->getJson('/api/v1/admin/users');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_request_without_key_is_rejected_401(): void
    {
        $response = $this->getJson('/api/v1/admin/users');

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'API キーが無効です。',
            'error_code' => 'INVALID_API_KEY',
            'status' => 401,
        ]);
    }

    public function test_request_with_wrong_key_is_rejected_401(): void
    {
        $response = $this->withHeader('X-API-KEY', 'wrong-key')
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(401);
        $response->assertExactJson([
            'message' => 'API キーが無効です。',
            'error_code' => 'INVALID_API_KEY',
            'status' => 401,
        ]);
    }

    public function test_request_with_empty_key_is_rejected_401(): void
    {
        $response = $this->withHeader('X-API-KEY', '')
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(401);
        $response->assertJsonPath('error_code', 'INVALID_API_KEY');
    }

    public function test_when_config_is_empty_returns_503(): void
    {
        config(['analytics-export.api_key' => '']);

        $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(503);
        $response->assertExactJson([
            'message' => 'API キー未設定',
            'error_code' => 'API_KEY_NOT_CONFIGURED',
            'status' => 503,
        ]);
    }

    public function test_when_config_is_null_returns_503(): void
    {
        config(['analytics-export.api_key' => null]);

        $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
            ->getJson('/api/v1/admin/users');

        $response->assertStatus(503);
        $response->assertJsonPath('error_code', 'API_KEY_NOT_CONFIGURED');
    }

    public function test_does_not_use_session_or_sanctum(): void
    {
        $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
            ->getJson('/api/v1/admin/users');

        $response->assertOk();
        $this->assertNull(session('_token'));
    }
}
