<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Admin\Invitation;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\InvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_issue_invitation_for_coach_role(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'newcoach@example.test',
            'role' => UserRole::Coach->value,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'newcoach@example.test',
            'role' => UserRole::Coach->value,
            'status' => UserStatus::Invited->value,
        ]);
        $this->assertDatabaseHas('invitations', [
            'email' => 'newcoach@example.test',
            'status' => InvitationStatus::Pending->value,
        ]);

        Mail::assertSent(InvitationMail::class);
    }

    public function test_admin_can_issue_invitation_for_student_role(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'newstudent@example.test',
            'role' => UserRole::Student->value,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'newstudent@example.test',
            'role' => UserRole::Student->value,
            'status' => UserStatus::Invited->value,
        ]);
    }

    public function test_cannot_issue_invitation_for_admin_role(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.invitations.store'), [
                'email' => 'wanna-be-admin@example.test',
                'role' => UserRole::Admin->value,
            ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'wanna-be-admin@example.test',
        ]);
        Mail::assertNothingSent();
    }

    public function test_dispatches_invitation_mail_and_inserts_status_log(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'newbie@example.test',
            'role' => UserRole::Student->value,
        ])->assertRedirect();

        $user = User::where('email', 'newbie@example.test')->first();
        $this->assertNotNull($user);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $user->id,
            'changed_by_user_id' => $admin->id,
            'status' => UserStatus::Invited->value,
            'changed_reason' => '新規招待',
        ]);

        Mail::assertSent(InvitationMail::class);
    }

    public function test_coach_cannot_issue_invitation(): void
    {
        Mail::fake();
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)->post(route('admin.invitations.store'), [
            'email' => 'a@example.test',
            'role' => UserRole::Student->value,
        ]);

        $response->assertForbidden();
        Mail::assertNothingSent();
    }
}
