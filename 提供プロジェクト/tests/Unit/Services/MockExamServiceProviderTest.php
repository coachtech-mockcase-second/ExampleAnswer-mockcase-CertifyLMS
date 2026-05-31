<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Contracts\WeaknessAnalysisServiceContract;
use App\Services\WeaknessAnalysisService;
use Tests\TestCase;

class MockExamServiceProviderTest extends TestCase
{
    public function test_weakness_analysis_service_contract_resolves_to_full_implementation(): void
    {
        $resolved = $this->app->make(WeaknessAnalysisServiceContract::class);

        $this->assertInstanceOf(WeaknessAnalysisService::class, $resolved);
    }
}
