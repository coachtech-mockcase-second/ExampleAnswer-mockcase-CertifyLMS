<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EnrollmentNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function assignCoach(Enrollment $enrollment, User $coach, User $admin): void
    {
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $enrollment->certification_id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_assigned_coach_can_create_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coach, $admin);

        $response = $this->actingAs($coach)->post(route('admin.enrollments.notes.store', $enrollment), [
            'body' => '今週のメモ',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'coach_user_id' => $coach->id,
            'body' => '今週のメモ',
        ]);
    }

    public function test_unassigned_coach_cannot_create_note(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($coach)->postJson(route('admin.enrollments.notes.store', $enrollment), [
            'body' => 'evil',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_create_note_on_any_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.enrollments.notes.store', $enrollment), [
            'body' => '管理者メモ',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'coach_user_id' => $admin->id,
        ]);
    }

    public function test_student_cannot_access_note_routes(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        $response = $this->actingAs($student)->postJson(route('admin.enrollments.notes.store', $enrollment), [
            'body' => 'evil',
        ]);

        // role:admin,coach Middleware で 403
        $response->assertForbidden();
    }

    public function test_coach_cannot_delete_other_coachs_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coachA = User::factory()->coach()->inProgress()->create();
        $coachB = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coachA, $admin);
        $this->assignCoach($enrollment, $coachB, $admin);

        $note = EnrollmentNote::factory()->for($enrollment)->create(['coach_user_id' => $coachA->id]);

        $response = $this->actingAs($coachB)->deleteJson(route('enrollment-notes.destroy', $note));

        $response->assertForbidden();
    }

    public function test_admin_can_delete_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coach, $admin);
        $note = EnrollmentNote::factory()->for($enrollment)->create(['coach_user_id' => $coach->id]);

        $response = $this->actingAs($admin)->delete(route('enrollment-notes.destroy', $note));

        $response->assertRedirect();
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    public function test_coach_can_delete_own_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coach, $admin);
        $note = EnrollmentNote::factory()->for($enrollment)->create(['coach_user_id' => $coach->id]);

        $response = $this->actingAs($coach)->delete(route('enrollment-notes.destroy', $note));

        $response->assertRedirect();
        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }
}
