<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThrottleResponseTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_KEY = 'test-analytics-api-key-32-chars-aaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['analytics-export.api_key' => self::VALID_KEY]);
        // throttle のカウンタは Cache に乗るので、各テスト前にリセット
        app(RateLimiter::class)->clear(sha1('throttle:60,1|127.0.0.1'));
    }

    public function test_under_limit_passes_through(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
                ->getJson('/api/v1/admin/users');
            $response->assertOk();
        }
    }

    public function test_over_limit_returns_429_json(): void
    {
        // 61 回連投で 60 req/min を超過 → 429 JSON。
        // RateLimiter のキーは Laravel が自動算出するため、Cache 直接 hit で十分。
        $finalResponse = null;
        for ($i = 0; $i < 61; $i++) {
            $finalResponse = $this->withHeader('X-API-KEY', self::VALID_KEY)
                ->getJson('/api/v1/admin/users');

            if ($finalResponse->status() === 429) {
                break;
            }
        }

        $this->assertNotNull($finalResponse);
        $this->assertSame(429, $finalResponse->status());
        $finalResponse->assertJson([
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'status' => 429,
        ]);
    }
}
