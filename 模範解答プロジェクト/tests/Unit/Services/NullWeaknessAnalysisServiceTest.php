<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\NullWeaknessAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NullWeaknessAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_weak_categories_returns_empty_collection(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->for(Certification::factory()->published())->create();

        $result = app(NullWeaknessAnalysisService::class)->getWeakCategories($enrollment);

        $this->assertTrue($result->isEmpty());
    }
}
