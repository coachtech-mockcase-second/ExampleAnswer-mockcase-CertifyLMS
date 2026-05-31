<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UserPolicy の ability × Role マトリクス検証。
 * 管理者向けユーザー運用 ability 5 種 (viewAny / view / withdraw / extendCourse / grantMeetingQuota) × 3 ロール = 15 ケース +
 * 本人のみ true となる updateSelf を別軸で検証する。
 * 「他者のロール / プロフィール変更」動線は LMS で提供しないため、update ability は Policy 側に存在しない。
 */
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者向けユーザー運用 ability × Role のマトリクス検証。
     * Admin のみ全 ability で true、Coach / Student は全 ability で false が期待値。
     */
    #[DataProvider('adminOnlyAbilityMatrix')]
    public function test_admin_only_abilities_match_role_expectation(
        string $actingRole,
        string $policyMethod,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $target = User::factory()->student()->create();
        $policy = new UserPolicy;

        // Act
        $result = $policy->{$policyMethod}($actor, $target);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$policyMethod} で ".($expected ? 'true' : 'false').' を返すべきだが反対の結果が返った',
        );
    }

    public function test_update_self_returns_true_when_target_is_same_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $policy = new UserPolicy;

        // Act
        $result = $policy->updateSelf($user, $user);

        // Assert
        $this->assertTrue($result, '本人による自身プロフィール編集は許可されるはず');
    }

    public function test_update_self_returns_true_for_each_role_on_self(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new UserPolicy;

        // Act & Assert: ロール問わず本人による自身編集は許可
        $this->assertTrue($policy->updateSelf($admin, $admin));
        $this->assertTrue($policy->updateSelf($coach, $coach));
        $this->assertTrue($policy->updateSelf($student, $student));
    }

    public function test_update_self_returns_false_when_target_is_different_user(): void
    {
        // Arrange
        $auth = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $policy = new UserPolicy;

        // Act
        $result = $policy->updateSelf($auth, $other);

        // Assert
        $this->assertFalse($result, '他人のプロフィール編集は許可されないはず');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function adminOnlyAbilityMatrix(): array
    {
        $abilities = ['viewAny', 'view', 'withdraw', 'extendCourse', 'grantMeetingQuota'];
        $roles = [
            'admin' => true,
            'coach' => false,
            'student' => false,
        ];

        $cases = [];
        foreach ($roles as $role => $expected) {
            foreach ($abilities as $ability) {
                $caseKey = $expected
                    ? "{$role} は {$ability} を実行できる"
                    : "{$role} は {$ability} を実行できない";
                $cases[$caseKey] = [$role, $ability, $expected];
            }
        }

        return $cases;
    }
}
