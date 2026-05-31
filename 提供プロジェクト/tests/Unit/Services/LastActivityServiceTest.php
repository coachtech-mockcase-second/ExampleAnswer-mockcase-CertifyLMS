<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\User;
use App\Services\LastActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LastActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_enrollment_has_no_activity(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $result = app(LastActivityService::class)->batchLastActivityFor(new Collection([$enrollment]));

        $this->assertArrayNotHasKey($enrollment->id, $result);
    }

    public function test_returns_max_of_learning_session_ended_at(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        [$part, $chapter, $section] = $this->buildContentChain($cert);

        LearningSession::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'started_at' => now()->subDays(3)->setTime(10, 0),
            'ended_at' => now()->subDays(3)->setTime(10, 30),
        ]);
        $later = now()->subDay()->setTime(15, 0)->setMicros(0);
        LearningSession::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'started_at' => $later->copy()->subMinutes(20),
            'ended_at' => $later,
        ]);

        $result = app(LastActivityService::class)->batchLastActivityFor(new Collection([$enrollment]));

        $this->assertArrayHasKey($enrollment->id, $result);
        $this->assertTrue($result[$enrollment->id]->equalTo($later));
    }

    public function test_returns_max_of_section_question_answer_when_no_session(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        [$part, $chapter, $section] = $this->buildContentChain($cert);
        $question = SectionQuestion::factory()->forSection($section)->create();

        $first = now()->subDays(3);
        $latest = now()->subHour()->setMicros(0);

        SectionQuestionAnswer::factory()->create([
            'user_id' => $student->id,
            'section_question_id' => $question->id,
            'answered_at' => $first,
        ]);
        SectionQuestionAnswer::factory()->create([
            'user_id' => $student->id,
            'section_question_id' => $question->id,
            'answered_at' => $latest,
        ]);

        $result = app(LastActivityService::class)->batchLastActivityFor(new Collection([$enrollment]));

        $this->assertArrayHasKey($enrollment->id, $result);
        $this->assertTrue($result[$enrollment->id]->equalTo($latest));
    }

    public function test_picks_latest_across_session_and_answer(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        [$part, $chapter, $section] = $this->buildContentChain($cert);
        $question = SectionQuestion::factory()->forSection($section)->create();

        $sessionEnd = now()->subDays(2)->setMicros(0);
        $answerAt = now()->subDay()->setMicros(0);

        LearningSession::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'started_at' => $sessionEnd->copy()->subMinutes(20),
            'ended_at' => $sessionEnd,
        ]);
        SectionQuestionAnswer::factory()->create([
            'user_id' => $student->id,
            'section_question_id' => $question->id,
            'answered_at' => $answerAt,
        ]);

        $result = app(LastActivityService::class)->batchLastActivityFor(new Collection([$enrollment]));

        $this->assertTrue($result[$enrollment->id]->equalTo($answerAt));
    }

    public function test_keys_results_per_enrollment_in_batch(): void
    {
        $cert = Certification::factory()->published()->create();
        [$part, $chapter, $section] = $this->buildContentChain($cert);

        $enrollment1 = Enrollment::factory()->for($cert)->learning()->create();
        $enrollment2 = Enrollment::factory()->for($cert)->learning()->create();

        $ts1 = now()->subDays(3)->setMicros(0);
        $ts2 = now()->subDay()->setMicros(0);

        LearningSession::factory()->create([
            'user_id' => $enrollment1->user_id,
            'enrollment_id' => $enrollment1->id,
            'section_id' => $section->id,
            'started_at' => $ts1->copy()->subMinutes(10),
            'ended_at' => $ts1,
        ]);
        LearningSession::factory()->create([
            'user_id' => $enrollment2->user_id,
            'enrollment_id' => $enrollment2->id,
            'section_id' => $section->id,
            'started_at' => $ts2->copy()->subMinutes(10),
            'ended_at' => $ts2,
        ]);

        $result = app(LastActivityService::class)
            ->batchLastActivityFor(new Collection([$enrollment1, $enrollment2]));

        $this->assertTrue($result[$enrollment1->id]->equalTo($ts1));
        $this->assertTrue($result[$enrollment2->id]->equalTo($ts2));
    }

    public function test_empty_collection_returns_empty_array(): void
    {
        $this->assertSame([], app(LastActivityService::class)->batchLastActivityFor(new Collection));
    }

    /**
     * 教材階層 Part → Chapter → Section を 1 つずつ作って返す。
     *
     * @return array{0: Part, 1: Chapter, 2: Section}
     */
    private function buildContentChain(Certification $cert): array
    {
        $part = Part::factory()->for($cert)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);

        return [$part, $chapter, $section];
    }
}
