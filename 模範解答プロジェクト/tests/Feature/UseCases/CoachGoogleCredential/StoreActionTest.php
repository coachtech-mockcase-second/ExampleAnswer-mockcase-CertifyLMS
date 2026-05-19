<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\CoachGoogleCredential;

use App\Exceptions\Mentoring\GoogleOAuthException;
use App\Models\CoachGoogleCredential;
use App\Models\User;
use App\Services\Google\GoogleOAuthService;
use App\UseCases\CoachGoogleCredential\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_new_credential_when_state_matches_authenticated_coach(): void
    {
        $coach = User::factory()->coach()->create();

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('exchangeCode')
                ->once()
                ->with('auth-code-xyz')
                ->andReturn([
                    'access_token' => 'ya29.test_access',
                    'refresh_token' => '1//test_refresh',
                ]);
        });

        $credential = app(StoreAction::class)(
            authUser: $coach,
            code: 'auth-code-xyz',
            state: ['coach_id' => $coach->id, 'redirect_path' => '/settings/profile'],
        );

        $this->assertSame($coach->id, $credential->coach_id);
        $this->assertSame('ya29.test_access', $credential->access_token);
        $this->assertSame('1//test_refresh', $credential->refresh_token);
        $this->assertSame('primary', $credential->calendar_id);
    }

    public function test_throws_when_state_coach_id_does_not_match_authenticated_user(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();

        $this->expectException(GoogleOAuthException::class);
        try {
            app(StoreAction::class)(
                authUser: $coach,
                code: 'auth-code-xyz',
                state: ['coach_id' => $otherCoach->id, 'redirect_path' => '/'],
            );
        } finally {
            $this->assertDatabaseCount('coach_google_credentials', 0);
        }
    }

    public function test_throws_when_refresh_token_is_missing(): void
    {
        $coach = User::factory()->coach()->create();

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('exchangeCode')->once()->andReturn([
                'access_token' => 'ya29.test_access',
                // refresh_token なし
            ]);
        });

        $this->expectException(GoogleOAuthException::class);
        app(StoreAction::class)(
            authUser: $coach,
            code: 'auth-code-xyz',
            state: ['coach_id' => $coach->id],
        );
    }

    public function test_restores_soft_deleted_credential_on_re_connect(): void
    {
        $coach = User::factory()->coach()->create();
        $existing = CoachGoogleCredential::factory()->forCoach($coach)->create([
            'access_token' => 'old_access',
            'refresh_token' => 'old_refresh',
        ]);
        $existing->delete();

        $this->mock(GoogleOAuthService::class, function (MockInterface $mock) {
            $mock->shouldReceive('exchangeCode')->once()->andReturn([
                'access_token' => 'new_access',
                'refresh_token' => 'new_refresh',
            ]);
        });

        $credential = app(StoreAction::class)(
            authUser: $coach,
            code: 'fresh-code',
            state: ['coach_id' => $coach->id],
        );

        $this->assertSame('new_access', $credential->access_token);
        $this->assertSame('new_refresh', $credential->refresh_token);
        $this->assertNull($credential->deleted_at);
    }
}
