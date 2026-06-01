<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PlanPolicy の viewAny ability × Role マトリクス検証。
 * Plan 一覧は admin のみが閲覧できる admin-only リソース。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('viewAnyMatrix')]
    public function test_view_any_matches_role_expectation(string $actingRole, bool $expected): void
    {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $policy = new PlanPolicy;

        // Act
        $result = $policy->viewAny($actor);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が viewAny で ".($expected ? 'true' : 'false').' を返すはず',
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function viewAnyMatrix(): array
    {
        return [
            'admin は viewAny を実行できる' => ['admin', true],
            'coach は viewAny を実行できない' => ['coach', false],
            'student は viewAny を実行できない' => ['student', false],
        ];
    }
}
