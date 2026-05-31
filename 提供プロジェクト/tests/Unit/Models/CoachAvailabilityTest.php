<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CoachAvailability モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 1 リレーション (coach) + 2 scope (active / forDay) + 2 cast (day_of_week int / is_active bool) を網羅する。
 * コーチの曜日 × 時刻の面談可能枠。
 */
class CoachAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_relation_returns_owner_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($coach)->create();

        // Act
        $owner = $availability->coach;

        // Assert
        $this->assertTrue($owner->is($coach));
    }

    public function test_scope_active_filters_only_active(): void
    {
        // Arrange
        $active = CoachAvailability::factory()->active()->create();
        CoachAvailability::factory()->inactive()->create();

        // Act
        $results = CoachAvailability::active()->get();

        // Assert
        $this->assertCount(1, $results, 'is_active = true の枠のみが抽出されるはず');
        $this->assertTrue($results->first()->is($active));
    }

    public function test_scope_for_day_filters_by_day_of_week(): void
    {
        // Arrange
        $monday = CoachAvailability::factory()->active()->onDay(1)->create();
        CoachAvailability::factory()->active()->onDay(2)->create();

        // Act
        $results = CoachAvailability::forDay(1)->get();

        // Assert
        $this->assertCount(1, $results, '指定曜日 (day_of_week=1) の枠のみが抽出されるはず');
        $this->assertTrue($results->first()->is($monday));
    }

    public function test_day_of_week_and_is_active_casts(): void
    {
        // Arrange
        $availability = CoachAvailability::factory()->active()->onDay(3)->create();

        // Act
        $fresh = $availability->fresh();

        // Assert
        $this->assertIsInt($fresh->day_of_week, 'day_of_week は integer にキャストされるはず');
        $this->assertSame(3, $fresh->day_of_week);
        $this->assertIsBool($fresh->is_active, 'is_active は boolean にキャストされるはず');
        $this->assertTrue($fresh->is_active);
    }
}
