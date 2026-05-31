<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * User モデルの主要リレーション・Scope・Cast・SoftDelete を検証する Unit テスト。
 * User は 24+ リレーションを持つため、Pivot を含む代表 9 リレーション +
 * scopeActive + 主要 6 cast (role / status enum + datetime + bool + hashed) + SoftDelete を網羅する。
 * 残りのリレーション (chatMembers, sentChatMessages, aiChatConversations 等) は対応する Feature 側のテストで間接検証される。
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollments_relation_returns_only_owner_enrollments(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $own = Enrollment::factory()->for($student)->create();
        Enrollment::factory()->create(); // 他人の enrollment、混入しないはず

        // Act
        $results = $student->enrollments;

        // Assert
        $this->assertCount(1, $results, '対象 user の enrollments のみが取得されるはず');
        $this->assertTrue($results->first()->is($own));
    }

    public function test_plan_relation_returns_assigned_plan(): void
    {
        // Arrange
        $plan = Plan::factory()->published()->create();
        $student = User::factory()->student()->withPlan($plan)->create();

        // Act
        $assigned = $student->plan;

        // Assert
        $this->assertNotNull($assigned, 'plan_id が set されている user は plan を取得できるはず');
        $this->assertTrue($assigned->is($plan));
    }

    public function test_default_enrollment_relation_returns_set_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $student->update(['default_enrollment_id' => $enrollment->id]);

        // Act
        $default = $student->fresh()->defaultEnrollment;

        // Assert
        $this->assertNotNull($default, 'default_enrollment_id が set されている user は defaultEnrollment を取得できるはず');
        $this->assertTrue($default->is($enrollment));
    }

    public function test_assigned_certifications_returns_only_active_pivot_rows(): void
    {
        // Arrange: coach に 2 件アサインし、1 件を unassigned 状態にする
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $activeCert = Certification::factory()->published()->create();
        $inactiveCert = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'certification_id' => $activeCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now()->subDay(),
        ]);
        CertificationCoachAssignment::create([
            'certification_id' => $inactiveCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now()->subDays(10),
            'unassigned_at' => now()->subDay(),
        ]);

        // Act
        $assigned = $coach->assignedCertifications;

        // Assert
        $this->assertCount(
            1,
            $assigned,
            'assignedCertifications は unassigned_at IS NULL の現役アサインのみ返すはず',
        );
        $this->assertTrue($assigned->first()->is($activeCert));
    }

    public function test_coaching_certification_ids_returns_array_of_active_ids(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        // Act
        $ids = $coach->coachingCertificationIds();

        // Assert
        $this->assertIsArray($ids);
        $this->assertContains($cert->id, $ids, '現役担当の certification ID が ID 配列に含まれるはず');
    }

    public function test_invitations_and_issued_invitations_distinguish_owner_vs_inviter(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $invited = User::factory()->invited()->create();
        Invitation::factory()->forUser($invited)->pending()->create([
            'invited_by_user_id' => $admin->id,
        ]);

        // Act
        $invitedSide = $invited->invitations;
        $inviterSide = $admin->issuedInvitations;

        // Assert
        $this->assertCount(1, $invitedSide, 'invited user 側で invitations が 1 件取得できるはず');
        $this->assertCount(1, $inviterSide, 'admin 側で issuedInvitations が 1 件取得できるはず');
    }

    public function test_switchable_enrollments_returns_only_learning_or_passed(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $learning = Enrollment::factory()->for($student)->learning()->create();
        $passed = Enrollment::factory()->for($student)->passed()->create();
        Enrollment::factory()->for($student)->failed()->create();

        // Act
        $switchable = $student->fresh()->switchableEnrollments;

        // Assert
        $this->assertCount(
            2,
            $switchable,
            'switchableEnrollments は Learning + Passed の enrollment のみ含むはず',
        );
        $statuses = $switchable->pluck('status')->all();
        $this->assertContains(EnrollmentStatus::Learning, $statuses);
        $this->assertContains(EnrollmentStatus::Passed, $statuses);
    }

    public function test_scope_active_returns_in_progress_and_graduated_users(): void
    {
        // Arrange
        User::factory()->invited()->create();
        $inProgress = User::factory()->inProgress()->create();
        $graduated = User::factory()->graduated()->create();
        User::factory()->withdrawn()->create();

        // Act
        $results = User::query()->active()->get();

        // Assert
        $this->assertCount(
            2,
            $results,
            'scope active は InProgress + Graduated の user のみ返すはず (invited / withdrawn は除外)',
        );
        $this->assertTrue($results->contains($inProgress));
        $this->assertTrue($results->contains($graduated));
    }

    public function test_role_and_status_casts_convert_to_enum(): void
    {
        // Arrange
        $user = User::factory()->coach()->inProgress()->create();

        // Act
        $fresh = $user->fresh();

        // Assert
        $this->assertInstanceOf(UserRole::class, $fresh->role, 'role カラムは UserRole enum にキャストされるはず');
        $this->assertSame(UserRole::Coach, $fresh->role);
        $this->assertInstanceOf(UserStatus::class, $fresh->status);
        $this->assertSame(UserStatus::InProgress, $fresh->status);
    }

    public function test_password_cast_is_hashed_automatically(): void
    {
        // Arrange
        $user = User::factory()->create(['password' => 'plain-text-password']);

        // Act
        $fresh = $user->fresh();

        // Assert
        $this->assertNotSame('plain-text-password', $fresh->password, 'password は hashed cast で平文保存されないはず');
        $this->assertTrue(Hash::check('plain-text-password', $fresh->password), 'Hash::check で元の平文と検証できるはず');
    }

    public function test_datetime_casts_return_carbon_instances(): void
    {
        // Arrange
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->student()->withPlan($plan)->create([
            'email_verified_at' => '2026-01-01 10:00:00',
        ]);

        // Act
        $fresh = $user->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->email_verified_at);
        $this->assertInstanceOf(Carbon::class, $fresh->plan_started_at, 'plan_started_at は Carbon にキャストされるはず');
        $this->assertInstanceOf(Carbon::class, $fresh->plan_expires_at);
    }

    public function test_boolean_and_integer_casts_return_native_types(): void
    {
        // Arrange
        $user = User::factory()->create([
            'profile_setup_completed' => 1,
            'max_meetings' => '8',
        ]);

        // Act
        $fresh = $user->fresh();

        // Assert
        $this->assertIsBool($fresh->profile_setup_completed, 'profile_setup_completed は boolean にキャストされるはず');
        $this->assertTrue($fresh->profile_setup_completed);
        $this->assertIsInt($fresh->max_meetings, 'max_meetings は integer にキャストされるはず');
        $this->assertSame(8, $fresh->max_meetings);
    }

    public function test_soft_delete_keeps_user_recoverable(): void
    {
        // Arrange
        $user = User::factory()->graduated()->create();
        $userId = $user->id;

        // Act
        $user->delete();

        // Assert
        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->assertNull(User::find($userId));
        $this->assertNotNull(
            User::withTrashed()->find($userId),
            'withTrashed で SoftDelete 済みの user を取得できるはず',
        );
    }
}
