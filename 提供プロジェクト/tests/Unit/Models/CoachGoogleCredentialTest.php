<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\CoachGoogleCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CoachGoogleCredential モデルのリレーション・Cast を検証する Unit テスト。
 * 1 リレーション (coach) + 1 cast (connected_at datetime) を網羅する。
 * コーチの Google Calendar OAuth 認証情報 (1 コーチ : 1 認証情報、連携解除時は物理削除)。
 */
class CoachGoogleCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_relation_returns_owner_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $credential = CoachGoogleCredential::factory()->forCoach($coach)->create();

        // Act
        $owner = $credential->coach;

        // Assert
        $this->assertTrue($owner->is($coach));
    }

    public function test_connected_at_cast_returns_carbon(): void
    {
        // Arrange
        $credential = CoachGoogleCredential::factory()->create([
            'connected_at' => '2026-05-20 10:00:00',
        ]);

        // Act
        $fresh = $credential->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->connected_at, 'connected_at は Carbon datetime にキャストされるはず');
    }
}
