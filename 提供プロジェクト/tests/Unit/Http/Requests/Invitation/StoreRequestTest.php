<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invitation;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 招待発行 FormRequest (`app/Http/Requests/Invitation/StoreRequest.php`) のバリデーション検証。
 * 受講生招待では Published Plan 必須 / コーチ招待では Plan 禁止という相互作用ルール (required_if / prohibited_if) と
 * Plan の exists 検証 (Published 状態のみ通過) を網羅する。authorize は admin のみ true。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_invitation_passes_with_published_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'newbie@example.test',
            'role' => UserRole::Student->value,
            'plan_id' => $plan->id,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('users', ['email' => 'newbie@example.test']);
    }

    public function test_coach_invitation_passes_without_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.invitations.store'), [
            'email' => 'coach@example.test',
            'role' => UserRole::Coach->value,
        ]);

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'email' => 'coach@example.test',
            'plan_id' => null,
        ]);
    }

    public function test_student_invitation_without_plan_fails_required_if(): void
    {
        // Arrange: 受講生招待で plan_id 未指定 → required_if 違反
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.invitations.store'), [
            'email' => 'newbie@example.test',
            'role' => UserRole::Student->value,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plan_id');
    }

    public function test_coach_invitation_with_plan_fails_prohibited_if(): void
    {
        // Arrange: コーチ招待で plan_id 指定 → prohibited_if 違反
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.invitations.store'), [
            'email' => 'coach@example.test',
            'role' => UserRole::Coach->value,
            'plan_id' => $plan->id,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plan_id');
    }

    public function test_student_invitation_with_draft_plan_fails_exists_rule(): void
    {
        // Arrange: 受講生招待で未公開 Plan を指定 → exists where status=Published に該当せず
        $admin = User::factory()->admin()->create();
        $draftPlan = Plan::factory()->create(['status' => PlanStatus::Draft]);

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.invitations.store'), [
            'email' => 'newbie@example.test',
            'role' => UserRole::Student->value,
            'plan_id' => $draftPlan->id,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plan_id');
    }

    public function test_student_invitation_with_nonexistent_plan_fails(): void
    {
        // Arrange: 受講生招待で存在しない ULID を指定
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.invitations.store'), [
            'email' => 'newbie@example.test',
            'role' => UserRole::Student->value,
            'plan_id' => (string) Str::ulid(),
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plan_id');
    }

    #[DataProvider('invalidEmailOrRolePayloads')]
    public function test_validation_fails_for_email_or_role(array $payload, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.invitations.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(route('admin.invitations.store'), [
            'email' => 'newbie@example.test',
            'role' => UserRole::Coach->value,
        ]);

        // Assert: ロール Middleware (`role:admin`) で 403 になる
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidEmailOrRolePayloads(): array
    {
        return [
            'メール未指定で 422'           => [['role' => 'coach'], 'email'],
            'メール形式不正で 422'         => [['email' => 'not-an-email', 'role' => 'coach'], 'email'],
            'メール 256 文字超で 422'      => [['email' => str_repeat('a', 247).'@example.test', 'role' => 'coach'], 'email'],
            'ロール未指定で 422'           => [['email' => 'x@example.test'], 'role'],
            'ロール許容外の値で 422'       => [['email' => 'x@example.test', 'role' => 'admin'], 'role'],
        ];
    }
}
