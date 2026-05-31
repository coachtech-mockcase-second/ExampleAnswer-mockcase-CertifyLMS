<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use App\Policies\CertificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CertificationPolicy の ability × Role × 資格状態のマトリクス検証。
 * viewAny (admin/coach=true / student=false) / view (admin=全件 / coach=担当のみ / student=published のみ) /
 * 管理系 8 ability (create / update / delete / publish / unpublish / archive / attachCoach / detachCoach、admin のみ true)
 * の 3 ブロックに分けて網羅する。
 *
 * 一覧画面の表示行絞込は Policy ではなく Eloquent local scope `Certification::scopeForUser(User)` の責務であり、
 * viewAny は coach に対して画面到達 (空一覧でも到達可) を許可する設計を併せて検証する。
 */
class CertificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('viewAnyMatrix')]
    public function test_view_any_returns_expected_for_role(string $actingRole, bool $expected): void
    {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $policy = new CertificationPolicy;

        // Act
        $result = $policy->viewAny($actor);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} の viewAny は ".($expected ? 'true' : 'false').' を返すはず',
        );
    }

    #[DataProvider('viewMatrix')]
    public function test_view_returns_expected_for_role_and_status(
        string $actingRole,
        string $certStatus,
        bool $assigned,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $cert = Certification::factory()->create(['status' => CertificationStatus::from($certStatus)]);
        if ($assigned && $actingRole === 'coach') {
            $admin = User::factory()->admin()->create();
            CertificationCoachAssignment::create([
                'id' => (string) Str::ulid(),
                'certification_id' => $cert->id,
                'user_id' => $actor->id,
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
            ]);
            $cert->load('coaches');
        }
        $policy = new CertificationPolicy;

        // Act
        $result = $policy->view($actor, $cert);

        // Assert
        $description = "{$actingRole} (assigned=".($assigned ? 'yes' : 'no').") の view({$certStatus})";
        $this->assertSame(
            $expected,
            $result,
            $description.' は '.($expected ? 'true' : 'false').' を返すはず',
        );
    }

    #[DataProvider('adminOnlyAbilityMatrix')]
    public function test_admin_only_abilities_match_role_expectation(
        string $actingRole,
        string $policyMethod,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $cert = Certification::factory()->published()->create();
        $policy = new CertificationPolicy;

        // Act
        $result = $policyMethod === 'create'
            ? $policy->create($actor)
            : $policy->{$policyMethod}($actor, $cert);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$policyMethod} で ".($expected ? 'true' : 'false').' を返すはず',
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function viewAnyMatrix(): array
    {
        return [
            'admin は一覧画面に到達できる' => ['admin', true],
            'coach は一覧画面に到達できる (担当 0 件でも到達可、行絞込は scopeForUser)' => ['coach', true],
            'student は一覧画面に到達できない (公開資格は別画面で閲覧)' => ['student', false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool, 3: bool}>
     */
    public static function viewMatrix(): array
    {
        return [
            'admin は draft も view 可'              => ['admin',   'draft',     false, true],
            'admin は published も view 可'          => ['admin',   'published', false, true],
            'admin は archived も view 可'           => ['admin',   'archived',  false, true],
            'coach は担当資格 (published) を view 可' => ['coach',   'published', true,  true],
            'coach は非担当資格を view 不可'         => ['coach',   'published', false, false],
            'student は published を view 可'        => ['student', 'published', false, true],
            'student は draft を view 不可'          => ['student', 'draft',     false, false],
            'student は archived を view 不可'       => ['student', 'archived',  false, false],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function adminOnlyAbilityMatrix(): array
    {
        $abilities = [
            'create', 'update', 'delete', 'publish', 'unpublish', 'archive', 'attachCoach', 'detachCoach',
        ];
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
