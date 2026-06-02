<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Availability;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 面談設定ページ (`settings.availability.index`) の描画と認可を検証する Feature テスト。
 * コーチは自分の面談可能時間枠ページを閲覧でき、他コーチの枠は混入しない。受講生 / 管理者は 403。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected(): void
    {
        // Arrange

        // Act
        $response = $this->get(route('settings.availability.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }

    public function test_coach_can_view_availability_page_with_own_slots(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $monday = CoachAvailability::factory()->forCoach($coach)->monday()->morning()->create();
        $mondayEve = CoachAvailability::factory()->forCoach($coach)->monday()->evening()->create();
        $sunday = CoachAvailability::factory()->forCoach($coach)->sunday()->morning()->create();

        // Act
        $response = $this->actingAs($coach)->get(route('settings.availability.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('settings.availability');

        $availabilities = $response->viewData('availabilities');
        $this->assertSame(3, $availabilities->count());
        $this->assertSame([
            $sunday->id,
            $monday->id,
            $mondayEve->id,
        ], $availabilities->pluck('id')->all());
    }

    public function test_coach_does_not_see_other_coaches_availabilities(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $other = User::factory()->coach()->create();
        CoachAvailability::factory()->forCoach($other)->monday()->create();

        // Act
        $response = $this->actingAs($coach)->get(route('settings.availability.index'));

        // Assert
        $response->assertOk();
        $this->assertSame(0, $response->viewData('availabilities')->count());
    }

    public function test_student_is_forbidden_on_availability_endpoint(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)->get(route('settings.availability.index'));

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_is_forbidden_on_availability_endpoint(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->get(route('settings.availability.index'));

        // Assert
        $response->assertForbidden();
    }
}
