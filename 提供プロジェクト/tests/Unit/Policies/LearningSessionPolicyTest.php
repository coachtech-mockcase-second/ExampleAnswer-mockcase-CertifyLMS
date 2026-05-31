<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\User;
use App\Policies\LearningSessionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allowed_for_own_session(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $session = LearningSession::factory()->forUser($student)->forEnrollment($enrollment)->create();

        $this->assertTrue(app(LearningSessionPolicy::class)->view($student, $session));
    }

    public function test_view_denied_for_other_session(): void
    {
        $student = User::factory()->student()->create();
        $session = LearningSession::factory()->create();

        $this->assertFalse(app(LearningSessionPolicy::class)->view($student, $session));
    }

    public function test_update_denied_for_other_session(): void
    {
        $student = User::factory()->student()->create();
        $session = LearningSession::factory()->create();

        $this->assertFalse(app(LearningSessionPolicy::class)->update($student, $session));
    }
}
