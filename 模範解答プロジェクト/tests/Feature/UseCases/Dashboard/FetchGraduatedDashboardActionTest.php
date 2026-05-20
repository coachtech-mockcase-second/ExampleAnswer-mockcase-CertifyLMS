<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Dashboard;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Dashboard\FetchGraduatedDashboardAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchGraduatedDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_passed_enrollments_ordered_by_passed_at_desc(): void
    {
        $graduated = User::factory()->student()->graduated()->create();
        $cert1 = Certification::factory()->published()->create(['name' => 'A']);
        $cert2 = Certification::factory()->published()->create(['name' => 'B']);
        Enrollment::factory()->for($graduated)->for($cert1)->passed()->create(['passed_at' => now()->subDays(5)]);
        Enrollment::factory()->for($graduated)->for($cert2)->passed()->create(['passed_at' => now()->subDay()]);
        Enrollment::factory()->for($graduated)->for(Certification::factory()->published()->create())->failed()->create();

        $vm = app(FetchGraduatedDashboardAction::class)($graduated);

        $this->assertCount(2, $vm->passedEnrollments);
        $names = $vm->passedEnrollments->map(fn (Enrollment $e) => $e->certification->name)->all();
        $this->assertSame(['B', 'A'], $names);
    }

    public function test_passed_enrollment_eager_loads_certificate(): void
    {
        $graduated = User::factory()->student()->graduated()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($graduated)->for($cert)->passed()->create(['passed_at' => now()]);
        Certificate::factory()->for($graduated)->for($enrollment)->for($cert)->create();

        $vm = app(FetchGraduatedDashboardAction::class)($graduated);

        $loaded = $vm->passedEnrollments->first();
        $this->assertTrue($loaded->relationLoaded('certificate'));
    }

    public function test_view_model_does_not_carry_v3_dropped_properties(): void
    {
        $graduated = User::factory()->student()->graduated()->create();

        $vm = app(FetchGraduatedDashboardAction::class)($graduated);

        $this->assertFalse(property_exists($vm, 'graduatedAt'));
        $this->assertFalse(property_exists($vm, 'certificateCount'));
        $this->assertFalse(property_exists($vm, 'planLocked'));
        $this->assertFalse(property_exists($vm, 'profileLink'));
    }
}
