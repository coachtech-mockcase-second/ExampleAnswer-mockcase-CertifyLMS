<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QuestionCategory;

use App\Exceptions\Content\QuestionCategoryInUseException;
use App\Models\Certification;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\UseCases\QuestionCategory\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_throws_in_use_exception_when_section_question_references_category(): void
    {
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = QuestionCategory::factory()->forCertification($cert)->create();
        SectionQuestion::factory()->forSection($section)->forCategory($category)->create();

        $action = app(DestroyAction::class);

        $this->expectException(QuestionCategoryInUseException::class);
        $action($category);
    }

    public function test_throws_in_use_exception_when_mock_exam_question_references_category(): void
    {
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->forCertification($cert)->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        MockExamQuestion::factory()
            ->forMockExam($mockExam)
            ->forCategory($category)
            ->create();

        $action = app(DestroyAction::class);

        $this->expectException(QuestionCategoryInUseException::class);
        $action($category);
    }

    public function test_soft_deletes_when_no_references(): void
    {
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->forCertification($cert)->create();

        $action = app(DestroyAction::class);
        $action($category);

        $this->assertSoftDeleted('question_categories', ['id' => $category->id]);
    }
}
