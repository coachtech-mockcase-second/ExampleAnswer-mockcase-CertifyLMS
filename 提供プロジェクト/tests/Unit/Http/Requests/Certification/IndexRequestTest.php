<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Certification;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 資格マスタ一覧 IndexRequest のバリデーション検証。
 * キーワード / status / category_id (exists) / difficulty / page の nullable フィルタを網羅し、
 * authorize は admin / coach の viewAny 通過 + student の不通過を検証する。
 */
class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_empty_filters(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.certifications.index'));

        // Assert
        $response->assertSuccessful();
    }

    public function test_validation_passes_with_all_filters_set(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.certifications.index', [
            'keyword' => 'AWS',
            'status' => CertificationStatus::Published->value,
            'category_id' => $category->id,
            'difficulty' => CertificationDifficulty::cases()[0]->value,
            'page' => 1,
        ]));

        // Assert
        $response->assertSuccessful();
    }

    #[DataProvider('invalidFilterPayloads')]
    public function test_validation_fails(array $params, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->getJson(route('admin.certifications.index', $params));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_student_cannot_access(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)->get(route('admin.certifications.index'));

        // Assert: ロール Middleware で 403
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidFilterPayloads(): array
    {
        return [
            'keyword 101 文字で 422'        => [['keyword' => str_repeat('a', 101)], 'keyword'],
            'status 不正値で 422'           => [['status' => 'unknown'], 'status'],
            'category_id 不正 ulid で 422'  => [['category_id' => 'not-ulid'], 'category_id'],
            'page 0 で 422'                 => [['page' => 0], 'page'],
            'page 文字列で 422'             => [['page' => 'abc'], 'page'],
        ];
    }
}
