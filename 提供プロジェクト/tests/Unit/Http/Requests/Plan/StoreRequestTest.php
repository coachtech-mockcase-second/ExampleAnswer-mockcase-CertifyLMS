<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plan 新規作成 FormRequest (`app/Http/Requests/Plan/StoreRequest.php`) のバリデーション検証。
 * 必須 / 型 / 文字数上限 / 数値レンジを valid 2 ケース + invalid 9 ケースで網羅し、authorize() で admin 以外を弾くことも検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('validPayloads')]
    public function test_validation_passes_with_valid_payload(array $payload): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $payload);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('plans', ['name' => $payload['name']]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails_with_invalid_payload(array $payload, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
        $this->assertDatabaseMissing('plans', ['name' => $payload['name'] ?? '']);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $payload = self::validBaseline();

        // Act
        $response = $this->actingAs($coach)->postJson(route('admin.plans.store'), $payload);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseMissing('plans', ['name' => $payload['name']]);
    }

    public function test_authorize_returns_false_for_guest(): void
    {
        // Arrange
        $payload = self::validBaseline();

        // Act
        $response = $this->post(route('admin.plans.store'), $payload);

        // Assert: 未認証は login へ redirect (ロール Middleware 到達前)
        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('plans', ['name' => $payload['name']]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function validPayloads(): array
    {
        return [
            '最小構成（必須項目のみ）' => [[
                'name' => 'Basic Plan',
                'duration_days' => 90,
                'default_meeting_quota' => 6,
            ]],
            '全項目（description / sort_order あり）' => [[
                'name' => 'Premium Plan',
                'description' => '長期プラン: 1 年間で資格 3 種に対応',
                'duration_days' => 365,
                'default_meeting_quota' => 24,
                'sort_order' => 10,
            ]],
        ];
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        $baseline = self::validBaseline();

        return [
            '名前未指定で 422'             => [['name' => '', 'duration_days' => 90, 'default_meeting_quota' => 6], 'name'],
            '名前が 101 文字で 422'        => [['name' => str_repeat('a', 101), 'duration_days' => 90, 'default_meeting_quota' => 6], 'name'],
            '説明が 2001 文字で 422'       => [array_merge($baseline, ['description' => str_repeat('b', 2001)]), 'description'],
            'duration_days 未指定で 422'   => [['name' => 'X', 'default_meeting_quota' => 6], 'duration_days'],
            'duration_days 0 で 422'       => [['name' => 'X', 'duration_days' => 0, 'default_meeting_quota' => 6], 'duration_days'],
            'duration_days 3651 で 422'    => [['name' => 'X', 'duration_days' => 3651, 'default_meeting_quota' => 6], 'duration_days'],
            'duration_days 非整数で 422'   => [['name' => 'X', 'duration_days' => 'abc', 'default_meeting_quota' => 6], 'duration_days'],
            'meeting_quota 負数で 422'     => [['name' => 'X', 'duration_days' => 90, 'default_meeting_quota' => -1], 'default_meeting_quota'],
            'meeting_quota 1001 で 422'    => [['name' => 'X', 'duration_days' => 90, 'default_meeting_quota' => 1001], 'default_meeting_quota'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validBaseline(): array
    {
        return [
            'name' => 'Baseline',
            'duration_days' => 90,
            'default_meeting_quota' => 6,
        ];
    }
}
