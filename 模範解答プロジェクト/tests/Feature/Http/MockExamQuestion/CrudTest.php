<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExamQuestion;

use App\Models\Certification;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_question_with_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        $this->actingAs($admin)
            ->post(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => '次のうち最も適切なものはどれか?',
                'explanation' => '解説テキスト',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                    ['body' => 'C', 'is_correct' => false, 'order' => 2],
                    ['body' => 'D', 'is_correct' => false, 'order' => 3],
                ],
            ])
            ->assertRedirect();

        $question = MockExamQuestion::firstOrFail();
        $this->assertSame($mockExam->id, $question->mock_exam_id);
        $this->assertSame(4, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
    }

    public function test_store_rejects_category_from_different_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $foreignCategory = QuestionCategory::factory()->state(['certification_id' => $otherCert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        $this->actingAs($admin)
            ->postJson(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => 'Q?',
                'category_id' => $foreignCategory->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_store_rejects_zero_correct_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        $this->actingAs($admin)
            ->postJson(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => false, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_store_rejects_multiple_correct_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        $this->actingAs($admin)
            ->postJson(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => true, 'order' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_store_rejects_options_outside_2_to_6_range(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        // 単一選択肢は拒否
        $this->actingAs($admin)
            ->postJson(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'options' => [['body' => 'A', 'is_correct' => true, 'order' => 0]],
            ])
            ->assertStatus(422);
    }

    public function test_store_assigns_max_order_plus_one(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        MockExamQuestion::factory()->forMockExam($mockExam)->forCategory($category)->withOptions()->state(['order' => 7])->create();

        $this->actingAs($admin)
            ->post(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => 'Q new',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertRedirect();

        $newQuestion = MockExamQuestion::where('body', 'Q new')->firstOrFail();
        $this->assertSame(8, $newQuestion->order);
    }

    public function test_coach_can_manage_question_for_assigned_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $category = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        $this->actingAs($coach)
            ->post(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => 'coach question',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mock_exam_questions', ['body' => 'coach question']);
    }

    public function test_unassigned_coach_cannot_create_question(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        $this->actingAs($coach)
            ->postJson(route('admin.mock-exams.questions.store', $mockExam), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertForbidden();
    }
}
