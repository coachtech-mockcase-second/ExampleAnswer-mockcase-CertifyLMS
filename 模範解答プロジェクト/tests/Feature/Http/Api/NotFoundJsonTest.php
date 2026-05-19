<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundJsonTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_KEY = 'test-analytics-api-key-32-chars-aaaa';

    protected function setUp(): void
    {
        parent::setUp();

        config(['analytics-export.api_key' => self::VALID_KEY]);
    }

    public function test_undefined_api_path_returns_404_json(): void
    {
        $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
            ->getJson('/api/v1/admin/undefined-path');

        $response->assertStatus(404);
        $response->assertJson([
            'error_code' => 'NOT_FOUND',
            'status' => 404,
        ]);
    }

    public function test_method_not_allowed_returns_405_json(): void
    {
        $response = $this->withHeader('X-API-KEY', self::VALID_KEY)
            ->postJson('/api/v1/admin/users');

        $response->assertStatus(405);
        $response->assertJsonPath('error_code', 'METHOD_NOT_ALLOWED');
    }
}
