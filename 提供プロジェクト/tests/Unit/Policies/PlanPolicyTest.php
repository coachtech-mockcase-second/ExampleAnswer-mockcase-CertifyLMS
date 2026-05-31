<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PlanPolicy の ability × Role マトリクス検証。
 * 全 8 ability (viewAny / view / create / update / delete / publish / archive / unarchive) × 3 ロール = 24 ケースを網羅する。
 * Plan は admin のみが CRUD + 状態遷移できる admin-only リソース。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Plan ability × Role の admin-only マトリクス検証。
     * admin: 全 true / coach: 全 false / student: 全 false が期待値。
     */
    #[DataProvider('adminOnlyAbilityMatrix')]
    public function test_ability_matches_role_expectation(
        string $actingRole,
        string $policyMethod,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $plan = Plan::factory()->published()->create();
        $policy = new PlanPolicy;

        // Act
        $result = $policyMethod === 'create' || $policyMethod === 'viewAny'
            ? $policy->{$policyMethod}($actor)
            : $policy->{$policyMethod}($actor, $plan);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$policyMethod} で ".($expected ? 'true' : 'false').' を返すはず',
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function adminOnlyAbilityMatrix(): array
    {
        $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'publish', 'archive', 'unarchive'];
        $roles = ['admin' => true, 'coach' => false, 'student' => false];
        $cases = [];
        foreach ($roles as $role => $expected) {
            foreach ($abilities as $ability) {
                $key = $expected
                    ? "{$role} は {$ability} を実行できる"
                    : "{$role} は {$ability} を実行できない";
                $cases[$key] = [$role, $ability, $expected];
            }
        }

        return $cases;
    }
}
