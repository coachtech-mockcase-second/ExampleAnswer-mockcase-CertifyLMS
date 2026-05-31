<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certification;

use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 旧仕様で存在した余分なフィールド（code / slug / passing_score 等）が
 * リクエストに紛れ込んでも DB に保存されない（rule で許容しない / fillable で弾く）ことを検証する。
 */
class InvalidColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_ignores_unknown_columns(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.store'), [
            'name' => '余分フィールド付き資格',
            'category_id' => $category->id,
            'difficulty' => 'beginner',
            'description' => '説明',
            'code' => 'CERT-IGNORED',
            'slug' => 'ignored-slug',
            'passing_score' => 70,
            'total_questions' => 80,
            'exam_duration_minutes' => 120,
        ]);

        $response->assertRedirect();

        $created = Certification::query()->where('name', '余分フィールド付き資格')->first();
        $this->assertNotNull($created);
        $this->assertSame(['id', 'name', 'category_id', 'difficulty', 'description', 'status', 'created_by_user_id', 'updated_by_user_id', 'published_at', 'archived_at', 'created_at', 'updated_at'], array_keys($created->getAttributes()));
    }
}
