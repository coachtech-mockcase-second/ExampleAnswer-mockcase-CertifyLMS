<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Policies\AiChatConversationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatConversationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_true_only_for_in_progress_student(): void
    {
        $policy = new AiChatConversationPolicy;

        $student = User::factory()->create(['role' => UserRole::Student->value, 'status' => UserStatus::InProgress->value]);
        $graduatedStudent = User::factory()->create(['role' => UserRole::Student->value, 'status' => UserStatus::Graduated->value]);
        $coach = User::factory()->create(['role' => UserRole::Coach->value]);
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->assertTrue($policy->viewAny($student));
        $this->assertFalse($policy->viewAny($graduatedStudent));
        $this->assertFalse($policy->viewAny($coach));
        $this->assertFalse($policy->viewAny($admin));
    }

    public function test_view_requires_ownership(): void
    {
        $policy = new AiChatConversationPolicy;
        $owner = User::factory()->create(['role' => UserRole::Student->value, 'status' => UserStatus::InProgress->value]);
        $other = User::factory()->create(['role' => UserRole::Student->value, 'status' => UserStatus::InProgress->value]);
        $conv = AiChatConversation::factory()->for($owner)->create();

        $this->assertTrue($policy->view($owner, $conv));
        $this->assertFalse($policy->view($other, $conv));
    }

    public function test_admin_cannot_view_any_student_conversation(): void
    {
        $policy = new AiChatConversationPolicy;
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $student = User::factory()->create(['role' => UserRole::Student->value, 'status' => UserStatus::InProgress->value]);
        $conv = AiChatConversation::factory()->for($student)->create();

        $this->assertFalse($policy->view($admin, $conv));
        $this->assertFalse($policy->update($admin, $conv));
        $this->assertFalse($policy->delete($admin, $conv));
    }
}
