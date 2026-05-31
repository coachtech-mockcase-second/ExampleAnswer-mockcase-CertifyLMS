<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Enums\TermType;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_not_started_session_with_question_snapshot(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();
        $q1 = MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create(['order' => 0]);
        $q2 = MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create(['order' => 1]);

        $this->actingAs($student)
            ->post(route('mock-exam.sessions.store', ['enrollment' => $enrollment, 'mockExam' => $mockExam]))
            ->assertRedirect();

        $session = MockExamSession::firstOrFail();
        $this->assertSame(MockExamSessionStatus::NotStarted, $session->status);
        $this->assertSame([$q1->id, $q2->id], $session->generated_question_ids);
        $this->assertSame(2, $session->total_questions);
        $this->assertSame($mockExam->passing_score, $session->passing_score_snapshot);
    }

    public function test_store_rejects_when_no_questions(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();

        $this->actingAs($student)
            ->postJson(route('mock-exam.sessions.store', ['enrollment' => $enrollment, 'mockExam' => $mockExam]))
            ->assertStatus(409);
    }

    public function test_store_rejects_duplicate_active_session(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();
        MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create();

        MockExamSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->inProgress()
            ->create();

        $this->actingAs($student)
            ->postJson(route('mock-exam.sessions.store', ['enrollment' => $enrollment, 'mockExam' => $mockExam]))
            ->assertStatus(409);
    }

    public function test_start_transitions_not_started_to_in_progress(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();
        MockExamQuestion::factory()->forMockExam($mockExam)->withOptions()->create();
        $session = MockExamSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->notStarted()
            ->create();

        $this->actingAs($student)
            ->post(route('mock-exam-sessions.start', $session))
            ->assertRedirect();

        $session->refresh();
        $this->assertSame(MockExamSessionStatus::InProgress, $session->status);
        $this->assertNotNull($session->started_at);
        $this->assertSame(TermType::MockPractice, $enrollment->refresh()->current_term);
    }

    public function test_start_rejects_when_mock_exam_unpublished(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create(['is_published' => false]);
        $session = MockExamSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->notStarted()
            ->create();

        $this->actingAs($student)
            ->postJson(route('mock-exam-sessions.start', $session))
            ->assertStatus(409);
    }

    public function test_start_rejects_when_already_in_progress(): void
    {
        $student = User::factory()->student()->create();
        $session = MockExamSession::factory()->forUser($student)->inProgress()->create();

        $this->actingAs($student)
            ->postJson(route('mock-exam-sessions.start', $session))
            ->assertStatus(409);
    }

    public function test_submit_grades_session_and_updates_term(): void
    {
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->passingScore(50)->create();

        // 4 問の問題 + 各 4 選択肢
        $questions = collect();
        for ($i = 0; $i < 4; $i++) {
            $questions->push(MockExamQuestion::factory()->forMockExam($mockExam)->withOptions(4, 0)->create(['order' => $i]));
        }

        $session = MockExamSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->inProgress()
            ->create([
                'generated_question_ids' => $questions->pluck('id')->all(),
                'total_questions' => 4,
                'passing_score_snapshot' => 50,
            ]);

        // 4 問中 3 問正解(75%、合格)
        foreach ($questions as $index => $question) {
            $correctOption = $question->options->firstWhere('is_correct', true);
            $wrongOption = $question->options->firstWhere('is_correct', false);
            $selectedOption = $index < 3 ? $correctOption : $wrongOption;

            MockExamAnswer::factory()->create([
                'mock_exam_session_id' => $session->id,
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $selectedOption->id,
                'selected_option_body' => $selectedOption->body,
                'is_correct' => false,
                'answered_at' => now(),
            ]);
        }

        $this->actingAs($student)
            ->post(route('mock-exam-sessions.submit', $session))
            ->assertRedirect();

        $session->refresh();
        $this->assertSame(MockExamSessionStatus::Graded, $session->status);
        $this->assertSame(3, $session->total_correct);
        $this->assertEquals(75.00, (float) $session->score_percentage);
        $this->assertTrue($session->pass);
        // 提出により実践ターム継続(submitted/graded は mock_practice 判定対象)
        $this->assertSame(TermType::MockPractice, $enrollment->refresh()->current_term);
    }

    public function test_submit_rejects_when_not_in_progress(): void
    {
        $student = User::factory()->student()->create();
        $session = MockExamSession::factory()->forUser($student)->notStarted()->create();

        $this->actingAs($student)
            ->postJson(route('mock-exam-sessions.submit', $session))
            ->assertStatus(409);
    }

    public function test_destroy_cancels_not_started_session(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->mockPractice()->create();
        $session = MockExamSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->notStarted()
            ->create();

        $this->actingAs($student)
            ->delete(route('mock-exam-sessions.destroy', $session))
            ->assertRedirect(route('mock-exam-sessions.index'));

        $session->refresh();
        $this->assertSame(MockExamSessionStatus::Canceled, $session->status);
        $this->assertNotNull($session->canceled_at);
        // TermJudgementService が basic_learning に戻る(他に進行中 mock がない場合)
        $this->assertSame(TermType::BasicLearning, $enrollment->refresh()->current_term);
    }

    public function test_destroy_rejects_in_progress_session(): void
    {
        $student = User::factory()->student()->create();
        $session = MockExamSession::factory()->forUser($student)->inProgress()->create();

        $this->actingAs($student)
            ->deleteJson(route('mock-exam-sessions.destroy', $session))
            ->assertStatus(409);
    }

    public function test_cannot_act_on_others_session(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $session = MockExamSession::factory()->forUser($other)->notStarted()->create();

        $this->actingAs($student)
            ->postJson(route('mock-exam-sessions.start', $session))
            ->assertForbidden();
    }
}
