<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MockExam;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 模試マスタ新規作成 FormRequest (`app/Http/Requests/MockExam/StoreRequest.php`) のバリデーション検証。
 * 必須 / 型 / 文字数 / 数値レンジ + Certification の exists 検証を網羅する。
 * authorize は Certification を引数にした MockExamPolicy::create を呼び、admin のみ通過することを併せて検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_full_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.mock-exams.store'), [
            'certification_id' => $cert->id,
            'title' => '基本情報技術者試験 模試 第1回',
            'description' => '午前科目を中心とした標準難易度の模試',
            'order' => 1,
            'passing_score' => 60,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('mock_exams', [
            'certification_id' => $cert->id,
            'title' => '基本情報技術者試験 模試 第1回',
            'passing_score' => 60,
        ]);
    }

    #[DataProvider('invalidFieldPayloads')]
    public function test_validation_fails(string $invalidField, mixed $invalidValue): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $payload = array_merge([
            'certification_id' => $cert->id,
            'title' => 'Sample',
            'order' => 0,
            'passing_score' => 60,
        ], [$invalidField => $invalidValue]);

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.mock-exams.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($invalidField);
    }

    public function test_authorize_returns_false_for_nonexistent_certification_id(): void
    {
        // Arrange: 実在しない ULID を渡す → authorize() が Certification::find で null を取得し false を返す
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.mock-exams.store'), [
            'certification_id' => (string) Str::ulid(),
            'title' => 'Sample',
            'order' => 0,
            'passing_score' => 60,
        ]);

        // Assert: rules の exists 検証より authorize() の事前 find チェックが先に弾く
        $response->assertForbidden();
    }

    public function test_authorize_returns_false_for_non_string_certification_id(): void
    {
        // Arrange: certification_id を文字列以外で送ると authorize() の is_string チェックが弾く
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.mock-exams.store'), [
            'certification_id' => 12345,
            'title' => 'Sample',
            'order' => 0,
            'passing_score' => 60,
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange: coach は MockExamPolicy::create で false が返るはず
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(route('admin.mock-exams.store'), [
            'certification_id' => $cert->id,
            'title' => 'Sample',
            'order' => 0,
            'passing_score' => 60,
        ]);

        // Assert: ロール Middleware で 403、または Policy で 403
        $response->assertForbidden();
    }

    public function test_authorize_returns_false_when_certification_id_missing(): void
    {
        // Arrange: certification_id を送らないと authorize() が false を返し 403 になる
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.mock-exams.store'), [
            'title' => 'Sample',
            'order' => 0,
            'passing_score' => 60,
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidFieldPayloads(): array
    {
        return [
            'title 未指定で 422'       => ['title', ''],
            'title 101 文字で 422'     => ['title', str_repeat('a', 101)],
            'description 2001 文字で 422' => ['description', str_repeat('b', 2001)],
            'order 負数で 422'         => ['order', -1],
            'order 65536 で 422'       => ['order', 65536],
            'order 非整数で 422'       => ['order', 'abc'],
            'passing_score 0 で 422'   => ['passing_score', 0],
            'passing_score 101 で 422' => ['passing_score', 101],
            'passing_score 非整数で 422' => ['passing_score', 'abc'],
        ];
    }
}
