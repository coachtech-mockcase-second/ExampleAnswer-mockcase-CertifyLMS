<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidInvitationTokenException;
use App\Models\Invitation;
use App\Models\User;
use App\UseCases\Auth\OnboardAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OnboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_user_status_log_with_active_status_on_onboarding(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);

        $result = app(OnboardAction::class)($invitation, [
            'name' => '太郎',
            'bio' => null,
            'password' => 'secret-pass',
        ]);

        $this->assertSame(UserStatus::InProgress, $result->status);
        $this->assertSame(InvitationStatus::Accepted, $invitation->fresh()->status);
        $this->assertTrue(Hash::check('secret-pass', $result->password));
        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $user->id,
            'status' => UserStatus::InProgress->value,
            'changed_by_user_id' => $user->id,
            'changed_reason' => 'オンボーディング完了',
        ]);
    }

    public function test_throws_invalid_invitation_for_expired_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        $invitation = Invitation::factory()
            ->forUser($user)
            ->pending()
            ->expiringAt(now()->subHour())
            ->create(['invited_by_user_id' => $admin->id]);

        $this->expectException(InvalidInvitationTokenException::class);

        app(OnboardAction::class)($invitation, [
            'name' => '太郎',
            'password' => 'secret-pass',
        ]);
    }
}
