<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Announcement 配信 StoreRequest のバリデーション検証。
 * target_type で AllStudents / Certification / User を切り替え、required_if + prohibited_unless の相互作用ルール
 * (target_certification_id / target_user_id) を網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_for_all_students_without_target_ids(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '年末メンテナンスのお知らせ',
            'body' => '12 月 30 日 22:00 から 30 分間メンテナンスを実施します。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
    }

    public function test_validation_passes_for_certification_target_with_id(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '資格別のお知らせ',
            'body' => '対象資格の受講生のみに通知します。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $cert->id,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_certification_target_without_id_fails_required_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::Certification->value,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('target_certification_id');
    }

    public function test_all_students_with_certification_id_fails_prohibited_unless(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => $cert->id,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('target_certification_id');
    }

    public function test_user_target_without_id_fails_required_if(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::User->value,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('target_user_id');
    }

    public function test_title_required_validation(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.announcements.store'), [
            'body' => '本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }
}
