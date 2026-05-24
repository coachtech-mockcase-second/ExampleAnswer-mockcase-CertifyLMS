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

        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
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

        $response = $this->actingAs($coach)->postJson(route('enrollments.notes.store', $enrollment), [
            'body' => 'evil',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_create_note_on_any_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), [
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

        $response = $this->actingAs($student)->postJson(route('enrollments.notes.store', $enrollment), [
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

    public function test_coach_can_update_own_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coach, $admin);
        $note = EnrollmentNote::factory()->for($enrollment)->create([
            'coach_user_id' => $coach->id,
            'body' => '旧メモ本文',
        ]);

        $response = $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), [
            'body' => '新メモ本文',
        ]);

        // 3 ロール共有の enrollments.show に redirect(画面統合済)
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '新メモ本文',
            'coach_user_id' => $coach->id,
        ]);
    }

    public function test_admin_can_update_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coach, $admin);
        $note = EnrollmentNote::factory()->for($enrollment)->create([
            'coach_user_id' => $coach->id,
            'body' => '旧メモ本文',
        ]);

        $response = $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), [
            'body' => '管理者による補記',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '管理者による補記',
        ]);
    }

    public function test_coach_cannot_update_other_coachs_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coachA = User::factory()->coach()->inProgress()->create();
        $coachB = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coachA, $admin);
        $this->assignCoach($enrollment, $coachB, $admin);
        $note = EnrollmentNote::factory()->for($enrollment)->create(['coach_user_id' => $coachA->id]);

        $response = $this->actingAs($coachB)->patchJson(route('enrollment-notes.update', $note), [
            'body' => 'evil',
        ]);

        $response->assertForbidden();
    }

    public function test_student_cannot_update_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $this->assignCoach($enrollment, $coach, $admin);
        $note = EnrollmentNote::factory()->for($enrollment)->create(['coach_user_id' => $coach->id]);

        $response = $this->actingAs($student)->patchJson(route('enrollment-notes.update', $note), [
            'body' => 'evil',
        ]);

        // role:admin,coach Middleware で 403
        $response->assertForbidden();
    }

    public function test_assigned_coach_can_get_edit_view(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->create();
        $this->assignCoach($enrollment, $coach, $admin);
        $note = EnrollmentNote::factory()->for($enrollment)->create(['coach_user_id' => $coach->id]);

        $response = $this->actingAs($coach)->get(route('enrollment-notes.edit', $note));

        $response->assertOk();
        $response->assertViewIs('enrollment-note.edit');
    }
}
