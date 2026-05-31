<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SectionQuestion;

use App\Models\Certification;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_admin_can_create_section_question_with_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);

        $this->actingAs($admin)
            ->post(route('admin.sections.questions.store', $section), [
                'body' => 'Markdown を安全に描画するために最も適切な処理はどれか?',
                'explanation' => 'XSS 対策として html_input=strip が有効。',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'プレーンテキストとして表示する', 'is_correct' => false, 'order' => 0],
                    ['body' => 'CommonMark で html_input=strip を有効化する', 'is_correct' => true, 'order' => 1],
                    ['body' => '何もせずそのまま表示する', 'is_correct' => false, 'order' => 2],
                    ['body' => 'iframe で囲む', 'is_correct' => false, 'order' => 3],
                ],
            ])
            ->assertRedirect();

        $question = SectionQuestion::firstOrFail();
        $this->assertSame('draft', $question->status->value);
        $this->assertSame($section->id, $question->section_id);
        $this->assertSame(4, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
    }

    public function test_store_rejects_zero_correct_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);

        $this->actingAs($admin)
            ->postJson(route('admin.sections.questions.store', $section), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => false, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_store_rejects_category_from_different_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $otherCert = Certification::factory()->published()->create();
        $foreignCategory = $this->makeCategory($otherCert);

        $this->actingAs($admin)
            ->postJson(route('admin.sections.questions.store', $section), [
                'body' => 'Q?',
                'category_id' => $foreignCategory->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'B', 'is_correct' => false, 'order' => 1],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_store_rejects_single_option(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);

        $this->actingAs($admin)
            ->postJson(route('admin.sections.questions.store', $section), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'A', 'is_correct' => true, 'order' => 0],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_update_replaces_options_via_delete_and_insert(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);
        $question = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($category)
            ->withOptions(4)
            ->draft()
            ->create();

        $originalOptionIds = $question->options()->pluck('id')->all();

        $this->actingAs($admin)
            ->patch(route('admin.section-questions.update', $question), [
                'body' => 'updated',
                'category_id' => $category->id,
                'options' => [
                    ['body' => 'new A', 'is_correct' => true, 'order' => 0],
                    ['body' => 'new B', 'is_correct' => false, 'order' => 1],
                    ['body' => 'new C', 'is_correct' => false, 'order' => 2],
                ],
            ])
            ->assertRedirect();

        $question->refresh();
        $this->assertSame(3, $question->options()->count());
        $this->assertSame(0, SectionQuestionOption::whereIn('id', $originalOptionIds)->count());
    }

    public function test_publish_requires_valid_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);

        $bad = SectionQuestion::factory()->forSection($section)->forCategory($category)->draft()->create();
        $bad->options()->create(['body' => 'lone', 'is_correct' => true, 'order' => 0]);

        $this->actingAs($admin)
            ->postJson(route('admin.section-questions.publish', $bad))
            ->assertStatus(409);

        $good = SectionQuestion::factory()->forSection($section)->forCategory($category)->withOptions(4)->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.section-questions.publish', $good))
            ->assertRedirect();
        $this->assertSame('published', $good->fresh()->status->value);
    }

    public function test_non_assigned_coach_cannot_view_section_questions(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);

        $this->actingAs($coach)
            ->get(route('admin.sections.questions.index', $section))
            ->assertForbidden();
    }

    public function test_destroy_soft_deletes_section_question(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = $this->makeCategory($cert);
        $question = SectionQuestion::factory()
            ->forSection($section)
            ->forCategory($category)
            ->draft()
            ->create();

        $this->actingAs($admin)
            ->delete(route('admin.section-questions.destroy', $question))
            ->assertRedirect();

        $this->assertDatabaseMissing('section_questions', ['id' => $question->id]);
    }
}
