<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QuizStats;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_stats(): void
    {
        $student = User::factory()->student()->create();
        [$enrollment, $question] = $this->buildEnrollmentWithQuestion($student);

        SectionQuestionAttempt::factory()->forUser($student)->forQuestion($question)
            ->state(['attempt_count' => 4, 'correct_count' => 3])->create();

        $this->actingAs($student)
            ->get(route('quiz.stats.index', $enrollment))
            ->assertOk();
    }

    public function test_other_student_returns_403(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($other)
            ->for(Certification::factory()->published())
            ->state(['status' => EnrollmentStatus::Learning->value])
            ->create();

        $this->actingAs($student)
            ->get(route('quiz.stats.index', $enrollment))
            ->assertForbidden();
    }

    /**
     * @return array{0: Enrollment, 1: SectionQuestion}
     */
    private function buildEnrollmentWithQuestion(User $student): array
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->state(['status' => EnrollmentStatus::Learning->value])->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        $question = SectionQuestion::factory()->forSection($section)->published()->create();

        return [$enrollment, $question];
    }
}
