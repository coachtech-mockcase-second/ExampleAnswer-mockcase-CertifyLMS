<?php

namespace Tests\Unit\Policies;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Policies\InvitationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function policy(): InvitationPolicy
    {
        return new InvitationPolicy();
    }

    public function test_admin_can_create_viewAny_revoke(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        $pending = Invitation::factory()->forUser($user)->pending()
            ->create(['invited_by_user_id' => $admin->id]);

        $this->assertTrue($this->policy()->viewAny($admin));
        $this->assertTrue($this->policy()->create($admin));
        $this->assertTrue($this->policy()->revoke($admin, $pending));
    }

    public function test_coach_and_student_cannot_create_viewAny_revoke(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $user = User::factory()->invited()->create();
        $pending = Invitation::factory()->forUser($user)->pending()
            ->create(['invited_by_user_id' => $admin->id]);

        foreach ([$coach, $student] as $other) {
            $this->assertFalse($this->policy()->viewAny($other));
            $this->assertFalse($this->policy()->create($other));
            $this->assertFalse($this->policy()->revoke($other, $pending));
        }
    }

    public function test_admin_cannot_revoke_already_accepted_invitation(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();
        $accepted = Invitation::factory()->forUser($user)->accepted()
            ->create(['invited_by_user_id' => $admin->id]);

        $this->assertSame(InvitationStatus::Accepted, $accepted->status);
        $this->assertFalse($this->policy()->revoke($admin, $accepted));
    }
}
