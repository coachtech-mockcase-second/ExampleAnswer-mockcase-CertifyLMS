<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Certificate モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 3 リレーション (user / enrollment / certification) + 1 scope (issuedThisMonth) + 1 cast (issued_at datetime) を網羅する。
 * 修了証の発行記録を表すモデル。
 */
class CertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $certificate = Certificate::factory()->for($user)->create();

        // Act
        $owner = $certificate->user;

        // Assert
        $this->assertTrue($owner->is($user));
    }

    public function test_enrollment_relation_returns_source_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->passed()->create();
        $certificate = Certificate::factory()->forEnrollment($enrollment)->create();

        // Act
        $source = $certificate->enrollment;

        // Assert
        $this->assertTrue($source->is($enrollment));
    }

    public function test_certification_relation_returns_target_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $certificate = Certificate::factory()->for($cert)->create();

        // Act
        $target = $certificate->certification;

        // Assert
        $this->assertTrue($target->is($cert));
    }

    public function test_scope_issued_this_month_filters_current_month(): void
    {
        // Arrange
        $thisMonth = Certificate::factory()->create(['issued_at' => now()]);
        Certificate::factory()->create(['issued_at' => now()->subMonths(2)]);

        // Act
        $results = Certificate::issuedThisMonth()->get();

        // Assert
        $this->assertCount(1, $results, '当月発行の修了証のみが抽出されるはず');
        $this->assertTrue($results->first()->is($thisMonth));
    }

    public function test_issued_at_cast_returns_carbon(): void
    {
        // Arrange
        $certificate = Certificate::factory()->create(['issued_at' => '2026-05-20 10:00:00']);

        // Act
        $fresh = $certificate->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->issued_at);
    }
}
