<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\CoachGoogleCredential;
use App\Models\User;
use App\Services\Google\GoogleOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CoachGoogleCredentialControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_proxies_to_oauth_url(): void
    {
        $coach = User::factory()->coach()->create();
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?client_id=stub';

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock) use ($authUrl) {
            $mock->shouldReceive('getAuthUrl')
                ->once()
                ->andReturn($authUrl);
        });

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.redirect'));

        $response->assertRedirect($authUrl);
    }

    public function test_redirect_is_blocked_for_non_coach(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($student)->get(route('settings.google-calendar.redirect'))->assertForbidden();
        $this->actingAs($admin)->get(route('settings.google-calendar.redirect'))->assertForbidden();
    }

    public function test_destroy_revokes_and_soft_deletes_credential(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = CoachGoogleCredential::factory()->forCoach($coach)->create();

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('revoke')->once()->andReturn(true);
        });

        $response = $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'meeting']));
        $this->assertSoftDeleted('coach_google_credentials', ['id' => $credential->id]);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $state = json_encode(['coach_id' => $otherCoach->id, 'redirect_path' => '/']);

        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback').'?code=xyz&state='.urlencode($state)
        );

        $response->assertStatus(400);
    }

    public function test_callback_redirects_to_meeting_tab_by_default(): void
    {
        $coach = User::factory()->coach()->create();
        $state = json_encode(['coach_id' => $coach->id]);

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('exchangeCode')
                ->once()
                ->andReturn([
                    'access_token' => 'access-stub',
                    'refresh_token' => 'refresh-stub',
                ]);
        });

        $response = $this->actingAs($coach)->get(
            route('settings.google-calendar.callback').'?code=xyz&state='.urlencode($state)
        );

        // state.redirect_path 未設定なら meeting タブへ
        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('/settings/profile?tab=meeting', $response->headers->get('Location'));
    }
}
