<?php

namespace Tests\Feature\Http\Admin\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create([
            'name' => 'Old Name',
            'passing_score' => 50,
        ]);

        $payload = [
            'code' => $cert->code,
            'category_id' => $cert->category_id,
            'name' => 'New Name',
            'slug' => $cert->slug,
            'description' => $cert->description,
            'difficulty' => $cert->difficulty->value,
            'passing_score' => 70,
            'total_questions' => $cert->total_questions,
            'exam_duration_minutes' => $cert->exam_duration_minutes,
        ];

        $response = $this->actingAs($admin)->put(route('admin.certifications.update', $cert), $payload);

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertDatabaseHas('certifications', [
            'id' => $cert->id,
            'name' => 'New Name',
            'passing_score' => 70,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_is_unchanged_even_if_payload_includes_status(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $payload = [
            'code' => $cert->code,
            'category_id' => $cert->category_id,
            'name' => $cert->name,
            'slug' => $cert->slug,
            'description' => $cert->description,
            'difficulty' => $cert->difficulty->value,
            'passing_score' => $cert->passing_score,
            'total_questions' => $cert->total_questions,
            'exam_duration_minutes' => $cert->exam_duration_minutes,
            'status' => 'draft', // 攻撃: 状態を勝手に変更しようとする
        ];

        $this->actingAs($admin)->put(route('admin.certifications.update', $cert), $payload);

        $this->assertSame('published', $cert->fresh()->status->value);
    }

    public function test_coach_cannot_update(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($coach)->put(route('admin.certifications.update', $cert), [
            'code' => $cert->code,
            'category_id' => $cert->category_id,
            'name' => 'Hack',
            'slug' => $cert->slug,
            'difficulty' => $cert->difficulty->value,
            'passing_score' => 60,
            'total_questions' => 80,
            'exam_duration_minutes' => 150,
        ]);

        $response->assertForbidden();
    }
}
