<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Certification;

use App\Enums\CertificationDifficulty;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 資格マスタ更新 UpdateRequest のバリデーション検証。
 * Store と同じルールを持つが route('certification') 解決を含む authorize の admin 通過 / non-admin 不通過を検証する。
 */
class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = CertificationCategory::factory()->create();

        // Act
        $response = $this->actingAs($admin)->put(route('admin.certifications.update', $cert), [
            'name' => '更新後の資格名',
            'category_id' => $category->id,
            'difficulty' => CertificationDifficulty::cases()[0]->value,
            'description' => '更新後の説明',
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('certifications', ['id' => $cert->id, 'name' => '更新後の資格名']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = CertificationCategory::factory()->create();
        $payload = array_merge([
            'name' => '資格名',
            'category_id' => $category->id,
            'difficulty' => CertificationDifficulty::cases()[0]->value,
        ], $overrides);

        // Act
        $response = $this->actingAs($admin)->putJson(route('admin.certifications.update', $cert), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $category = CertificationCategory::factory()->create();

        // Act
        $response = $this->actingAs($coach)->putJson(route('admin.certifications.update', $cert), [
            'name' => '上書き',
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
            'difficulty 不正値で 422' => [['difficulty' => 'unknown'], 'difficulty'],
            'category_id ulid 不正で 422' => [['category_id' => 'not-ulid'], 'category_id'],
        ];
    }
}
