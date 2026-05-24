<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CertificationCoachAssignment (Pivot モデル) の Cast を検証する Unit テスト。
 * 2 cast (assigned_at / unassigned_at datetime) を網羅する。
 * 資格 × コーチの N:N 担当割当を表し、unassigned_at IS NULL が現役アサインを示す。
 */
class CertificationCoachAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_at_and_unassigned_at_casts(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignment = CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => '2026-05-01 10:00:00',
            'unassigned_at' => '2026-05-20 10:00:00',
        ]);

        // Act
        $fresh = $assignment->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->assigned_at, 'assigned_at は Carbon datetime にキャストされるはず');
        $this->assertInstanceOf(Carbon::class, $fresh->unassigned_at, 'unassigned_at は Carbon datetime にキャストされるはず');
    }

    public function test_unassigned_at_is_null_for_active_assignment(): void
    {
        // Arrange: 現役アサインは unassigned_at が null
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignment = CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        // Act
        $fresh = $assignment->fresh();

        // Assert
        $this->assertNull($fresh->unassigned_at, '現役アサインは unassigned_at が null のはず');
    }
}
