<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\SectionQuestionAttempt;
use App\Models\User;
use App\Policies\SectionQuestionAttemptPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionQuestionAttemptPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_allows_owner_only(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $attempt = SectionQuestionAttempt::factory()->forUser($student)->create();

        $policy = app(SectionQuestionAttemptPolicy::class);
        $this->assertTrue($policy->view($student, $attempt));
        $this->assertFalse($policy->view($other, $attempt));
    }
}
