<?php

namespace Tests\Feature\Http\Admin\QuestionCategory;

use App\Models\Certification;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_admin_can_create_question_category(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.question-categories.store', $cert), [
                'name' => 'テクノロジー系',
                'slug' => 'technology',
                'sort_order' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_categories', [
            'certification_id' => $cert->id,
            'slug' => 'technology',
        ]);
    }

    public function test_duplicate_slug_within_certification_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        QuestionCategory::factory()->forCertification($cert)->state(['slug' => 'tech'])->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.question-categories.store', $cert), [
                'name' => '別名',
                'slug' => 'tech',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_same_slug_in_different_certification_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $certA = Certification::factory()->published()->create();
        $certB = Certification::factory()->published()->create();
        QuestionCategory::factory()->forCertification($certA)->state(['slug' => 'tech'])->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.question-categories.store', $certB), [
                'name' => 'Tech',
                'slug' => 'tech',
            ])
            ->assertRedirect();

        $this->assertSame(2, QuestionCategory::where('slug', 'tech')->count());
    }

    public function test_destroy_blocked_if_questions_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->forCertification($cert)->create();
        Question::factory()->forCertification($cert)->forCategory($category)->create();

        $this->actingAs($admin)
            ->delete(route('admin.question-categories.destroy', $category))
            ->assertStatus(409);
    }

    public function test_destroy_allowed_when_no_questions(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->forCertification($cert)->create();

        $this->actingAs($admin)
            ->delete(route('admin.question-categories.destroy', $category))
            ->assertRedirect();

        $this->assertSoftDeleted('question_categories', ['id' => $category->id]);
    }

    public function test_non_assigned_coach_cannot_view(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($coach)
            ->get(route('admin.certifications.question-categories.index', $cert))
            ->assertForbidden();
    }
}
