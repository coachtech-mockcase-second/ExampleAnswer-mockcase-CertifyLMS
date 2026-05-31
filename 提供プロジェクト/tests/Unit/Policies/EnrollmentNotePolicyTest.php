<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EnrollmentNotePolicy の判定を検証する Unit テスト。
 * 受講生は閲覧・操作とも不可（指導記録なので非公開）/ admin は全可 / coach は担当資格の note のみ閲覧、自分の note のみ更新削除。
 */
class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->viewAny($admin, $enrollment));
        $this->assertTrue($policy->view($admin, $note));
        $this->assertTrue($policy->create($admin, $enrollment));
        $this->assertTrue($policy->update($admin, $note));
        $this->assertTrue($policy->delete($admin, $note));
    }

    public function test_coach_can_view_only_assigned_enrollment(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedEnrollment = Enrollment::factory()->for($assignedCert)->learning()->create();
        $otherEnrollment = Enrollment::factory()->for($otherCert)->learning()->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->viewAny($coach, $assignedEnrollment));
        $this->assertFalse($policy->viewAny($coach, $otherEnrollment));
    }

    public function test_student_cannot_access_any_notes(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertFalse($policy->viewAny($student, $enrollment), '指導記録なので受講生は本人の note も閲覧不可');
        $this->assertFalse($policy->view($student, $note));
        $this->assertFalse($policy->create($student, $enrollment));
    }

    public function test_coach_can_modify_only_own_note(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $admin = User::factory()->admin()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $ownNote = EnrollmentNote::factory()->for($enrollment)->for($coach, 'author')->create();
        $policy = new EnrollmentNotePolicy;

        $this->assertTrue($policy->update($coach, $ownNote), '自分の note は更新可');
        $this->assertFalse($policy->update($otherCoach, $ownNote), '他コーチの note は更新不可');
    }
}
