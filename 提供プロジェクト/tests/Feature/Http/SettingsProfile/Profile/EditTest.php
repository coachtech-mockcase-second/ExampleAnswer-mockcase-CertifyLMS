<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get(route('settings.profile.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_student_sees_own_profile_with_two_tabs(): void
    {
        $student = User::factory()->student()->inProgress()->create([
            'name' => '受講生 太郎',
            'bio' => '基本情報技術者試験を目指しています',
        ]);

        $response = $this->actingAs($student)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertViewIs('settings.profile');
        $response->assertSeeText('プロフィール設定');
        $response->assertSeeText('プロフィール');
        $response->assertSeeText('パスワード');
        $response->assertSeeText('受講生 太郎');
        $response->assertSeeText('基本情報技術者試験を目指しています');
        $response->assertDontSeeText('退会');
    }

    public function test_coach_sees_meeting_url_field(): void
    {
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        $response = $this->actingAs($coach)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertSeeText('固定面談 URL');
        $response->assertSee('https://meet.google.com/abc-defg-hij');
    }

    public function test_student_does_not_see_meeting_url_field(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertDontSeeText('固定面談 URL');
    }

    public function test_admin_does_not_see_meeting_url_field(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertDontSeeText('固定面談 URL');
    }

    public function test_password_tab_renders_when_tab_query_is_password(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('settings.profile.edit', ['tab' => 'password']));

        $response->assertOk();
        $response->assertSeeText('現在のパスワード');
        $response->assertSeeText('新しいパスワード');
    }

    public function test_coach_sees_meeting_tab_in_tab_list(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertSeeText('面談設定');
    }

    public function test_student_does_not_see_meeting_tab(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertDontSeeText('面談設定');
    }

    public function test_admin_does_not_see_meeting_tab(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertDontSeeText('面談設定');
    }

    public function test_meeting_tab_renders_calendar_for_coach(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->get(route('settings.profile.edit', ['tab' => 'meeting']));

        $response->assertOk();
        $response->assertSeeText('面談可能時間枠');
        $response->assertSeeText('時間枠を追加');
    }

    public function test_meeting_tab_query_for_non_coach_falls_back_to_profile_tab(): void
    {
        $student = User::factory()->student()->create([
            'name' => '受講生',
            'bio' => '自己紹介テキスト',
        ]);

        $response = $this->actingAs($student)->get(route('settings.profile.edit', ['tab' => 'meeting']));

        $response->assertOk();
        $response->assertSeeText('プロフィール情報');
        $response->assertDontSeeText('面談設定');
    }
}
