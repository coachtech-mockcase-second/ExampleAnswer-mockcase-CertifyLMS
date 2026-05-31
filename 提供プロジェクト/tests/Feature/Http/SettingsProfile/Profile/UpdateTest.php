<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->patch(route('settings.profile.update'), [
            'name' => '更新後',
            'bio' => 'bio',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_student_can_update_name_and_bio(): void
    {
        $student = User::factory()->student()->create([
            'name' => '更新前',
            'bio' => '更新前 bio',
        ]);

        $response = $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '受講生 太郎',
            'bio' => '新しい自己紹介',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $response->assertSessionHas('success', 'プロフィールを更新しました。');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'name' => '受講生 太郎',
            'bio' => '新しい自己紹介',
        ]);
    }

    public function test_student_meeting_url_is_silently_dropped(): void
    {
        $student = User::factory()->student()->create([
            'name' => '受講生',
            'meeting_url' => null,
        ]);

        $response = $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '受講生',
            'bio' => null,
            'meeting_url' => 'https://meet.google.com/student-attempt',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'meeting_url' => null,
        ]);
    }

    public function test_admin_meeting_url_is_silently_dropped(): void
    {
        $admin = User::factory()->admin()->create([
            'meeting_url' => null,
        ]);

        $this->actingAs($admin)->patch(route('settings.profile.update'), [
            'name' => '管理者',
            'meeting_url' => 'https://meet.google.com/admin-attempt',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'meeting_url' => null,
        ]);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = User::factory()->coach()->create([
            'meeting_url' => null,
        ]);

        $response = $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => 'コーチ',
            'meeting_url' => 'https://meet.google.com/coach-room',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'profile']));
        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => 'https://meet.google.com/coach-room',
        ]);
    }

    public function test_coach_meeting_url_can_be_cleared_with_empty_string(): void
    {
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.google.com/old-room',
        ]);

        $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => 'コーチ',
            'meeting_url' => '',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $coach->id,
            'meeting_url' => null,
        ]);
    }

    public function test_email_is_not_updated_even_if_submitted(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'original@example.com',
            'name' => '受講生',
        ]);

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '受講生',
            'email' => 'attacker@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'email' => 'original@example.com',
        ]);
    }

    public function test_validation_fails_when_name_is_empty(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => '',
                'bio' => 'something',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHasErrors(['name']);
    }

    public function test_validation_fails_when_name_exceeds_50_chars(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => str_repeat('あ', 51),
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_validation_fails_when_bio_exceeds_1000_chars(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => '受講生',
                'bio' => str_repeat('a', 1001),
            ]);

        $response->assertSessionHasErrors(['bio']);
    }

    public function test_validation_fails_when_coach_meeting_url_is_invalid(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => 'コーチ',
                'meeting_url' => 'not-a-valid-url',
            ]);

        $response->assertSessionHasErrors(['meeting_url']);
    }
}
