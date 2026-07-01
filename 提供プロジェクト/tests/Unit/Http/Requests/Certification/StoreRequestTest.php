<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Certification;

use App\Enums\CertificationDifficulty;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 資格マスタ新規作成 StoreRequest のバリデーション検証。
 * 必須 name / category_id (exists) / difficulty (enum) / description の組合せを valid + invalid で網羅し、
 * authorize は admin のみ true を検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.certifications.store'), [
            'name' => 'AWS Certified Solutions Architect',
            'category_id' => $category->id,
            'difficulty' => CertificationDifficulty::cases()[0]->value,
            'description' => '対象範囲: VPC / IAM / S3 / EC2',
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('certifications', ['name' => 'AWS Certified Solutions Architect']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();
        $payload = array_merge([
            'name' => 'Sample Cert',
            'category_id' => $category->id,
            'difficulty' => CertificationDifficulty::cases()[0]->value,
        ], $overrides);

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.certifications.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $category = CertificationCategory::factory()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(route('admin.certifications.store'), [
            'name' => 'Sample',
            'category_id' => $category->id,
            'difficulty' => CertificationDifficulty::cases()[0]->value,
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'name 未指定で 422' => [['name' => ''], 'name'],
            'name 101 文字で 422' => [['name' => str_repeat('a', 101)], 'name'],
            'category_id ulid 不正で 422' => [['category_id' => 'not-ulid'], 'category_id'],
            'category_id 存在しない ulid で 422' => [['category_id' => (string) Str::ulid()], 'category_id'],
            'difficulty 不正値で 422' => [['difficulty' => 'unknown'], 'difficulty'],
            'description 1001 文字で 422' => [['description' => str_repeat('b', 1001)], 'description'],
        ];
    }
}
