<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QuestionCategory;

use App\Exceptions\Content\QuestionCategoryInUseException;
use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\UseCases\QuestionCategory\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $this->createMockExamQuestionsTableIfMissing();

        DB::table('mock_exam_questions')->insert([
            'id' => (string) Str::ulid(),
            'category_id' => $category->id,
            'body' => 'mock exam question',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

    private function createMockExamQuestionsTableIfMissing(): void
    {
        if (Schema::hasTable('mock_exam_questions')) {
            return;
        }

        Schema::create('mock_exam_questions', function ($table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')->constrained('question_categories');
            $table->text('body');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }
}
