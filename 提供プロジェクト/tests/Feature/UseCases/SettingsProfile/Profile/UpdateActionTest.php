<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\SettingsProfile\Profile;

use App\Models\User;
use App\UseCases\Profile\UpdateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_name_and_bio_for_student(): void
    {
        $student = User::factory()->student()->create([
            'name' => '更新前',
            'bio' => '更新前 bio',
            'meeting_url' => null,
        ]);

        $action = app(UpdateAction::class);
        $action($student, [
            'name' => '受講生',
            'bio' => '更新後 bio',
        ]);

        $fresh = $student->fresh();
        $this->assertSame('受講生', $fresh->name);
        $this->assertSame('更新後 bio', $fresh->bio);
        $this->assertNull($fresh->meeting_url);
    }

    public function test_silently_drops_meeting_url_for_non_coach(): void
    {
        $student = User::factory()->student()->create(['meeting_url' => null]);
        $action = app(UpdateAction::class);

        $action($student, [
            'name' => '受講生',
            'bio' => null,
            'meeting_url' => 'https://meet.google.com/student',
        ]);

        $this->assertNull($student->fresh()->meeting_url);
    }

    public function test_silently_drops_meeting_url_for_admin(): void
    {
        $admin = User::factory()->admin()->create(['meeting_url' => null]);
        $action = app(UpdateAction::class);

        $action($admin, [
            'name' => '管理者',
            'meeting_url' => 'https://meet.google.com/admin',
        ]);

        $this->assertNull($admin->fresh()->meeting_url);
    }

    public function test_updates_meeting_url_for_coach(): void
    {
        $coach = User::factory()->coach()->create(['meeting_url' => null]);
        $action = app(UpdateAction::class);

        $action($coach, [
            'name' => 'コーチ',
            'meeting_url' => 'https://meet.google.com/coach',
        ]);

        $this->assertSame('https://meet.google.com/coach', $coach->fresh()->meeting_url);
    }

    public function test_clears_meeting_url_for_coach_when_empty_string(): void
    {
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.google.com/old',
        ]);
        $action = app(UpdateAction::class);

        $action($coach, [
            'name' => 'コーチ',
            'meeting_url' => '',
        ]);

        $this->assertNull($coach->fresh()->meeting_url);
    }

    public function test_does_not_change_meeting_url_for_coach_when_key_absent(): void
    {
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.google.com/keep',
        ]);
        $action = app(UpdateAction::class);

        $action($coach, [
            'name' => 'コーチ',
            'bio' => null,
        ]);

        $this->assertSame('https://meet.google.com/keep', $coach->fresh()->meeting_url);
    }
}
