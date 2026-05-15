<?php

namespace Tests\Feature\Http\Admin\Question;

use App\Models\Certification;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuestionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_admin_can_create_question_with_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = $this->makeCategory($cert);

        $this->actingAs($admin)
            ->post(route('admin.certifications.questions.store', $cert), [
                'body' => 'Markdown を安全に描画するために最も適切な処理はどれか?',
                'explanation' => 'XSS 対策として html_input=strip が有効。',
                'category_id' => $category->id,
                'difficulty' => 'medium',
                'options' => [
                    ['body' => 'プレーンテキストとして表示する', 'is_correct' => false],
                    ['body' => 'CommonMark で html_input=strip を有効化する', 'is_correct' => true],
                    ['body' => '何もせずそのまま表示する', 'is_correct' => false],
                    ['body' => 'iframe で囲む', 'is_correct' => false],
                ],
            ])
            ->assertRedirect();

        $question = Question::firstOrFail();
        $this->assertSame('draft', $question->status->value);
        $this->assertSame(4, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
    }

    public function test_store_rejects_zero_correct_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = $this->makeCategory($cert);

        $this->actingAs($admin)
            ->post(route('admin.certifications.questions.store', $cert), [
                'body' => 'Q?',
                'category_id' => $category->id,
                'difficulty' => 'easy',
                'options' => [
                    ['body' => 'A', 'is_correct' => false],
                    ['body' => 'B', 'is_correct' => false],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_store_rejects_category_from_different_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $foreignCategory = $this->makeCategory($otherCert);

        $this->actingAs($admin)
            ->post(route('admin.certifications.questions.store', $cert), [
                'body' => 'Q?',
                'category_id' => $foreignCategory->id,
                'difficulty' => 'easy',
                'options' => [
                    ['body' => 'A', 'is_correct' => true],
                    ['body' => 'B', 'is_correct' => false],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_update_replaces_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = $this->makeCategory($cert);
        $question = Question::factory()
            ->forCertification($cert)
            ->forCategory($category)
            ->withOptions(4)
            ->draft()
            ->create();

        $originalOptionIds = $question->options()->pluck('id')->all();

        $this->actingAs($admin)
            ->patch(route('admin.questions.update', $question), [
                'body' => 'updated',
                'category_id' => $category->id,
                'difficulty' => 'hard',
                'options' => [
                    ['body' => 'new A', 'is_correct' => true],
                    ['body' => 'new B', 'is_correct' => false],
                    ['body' => 'new C', 'is_correct' => false],
                ],
            ])
            ->assertRedirect();

        $question->refresh();
        $this->assertSame(3, $question->options()->count());
        $this->assertSame(0, QuestionOption::whereIn('id', $originalOptionIds)->count());
    }

    public function test_publish_requires_valid_options(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = $this->makeCategory($cert);

        // 選択肢が 1 件しかない → 公開不可
        $bad = Question::factory()->forCertification($cert)->forCategory($category)->draft()->create();
        $bad->options()->create(['body' => 'lone', 'is_correct' => true, 'order' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.questions.publish', $bad))
            ->assertStatus(409);

        // 正常: 4 件 + 正答 1 → 公開できる
        $good = Question::factory()->forCertification($cert)->forCategory($category)->withOptions(4)->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.questions.publish', $good))
            ->assertRedirect();
        $this->assertSame('published', $good->fresh()->status->value);
    }
}
