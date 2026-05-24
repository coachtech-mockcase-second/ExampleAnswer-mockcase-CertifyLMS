<?php

declare(strict_types=1);

namespace Tests\Feature\Http\CoachStudent;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 統合後 `enrollments.index` のコーチ scope 観点テスト。
 * Policy + `Enrollment::scopeForUser` で担当資格の Enrollment のみが見えることを保証する。
 * admin / student の正常系・未認証拒否は `EnrollmentControllerTest` に集約済。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_sees_only_enrollments_in_assigned_certifications(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $assignedCert = Certification::factory()->published()->create(['name' => 'Assigned Cert']);
        $otherCert = Certification::factory()->published()->create(['name' => 'Other Cert']);

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        Enrollment::factory()->learning()->create([
            'user_id' => $student->id,
            'certification_id' => $assignedCert->id,
        ]);
        Enrollment::factory()->learning()->create([
            'user_id' => $student->id,
            'certification_id' => $otherCert->id,
        ]);

        // Act
        $response = $this->actingAs($coach)->get(route('enrollments.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('Assigned Cert');
        $response->assertDontSee('Other Cert');
    }

    public function test_guest_cannot_access(): void
    {
        // Act
        $response = $this->get(route('enrollments.index'));

        // Assert
        $response->assertRedirect(route('login'));
    }
}
