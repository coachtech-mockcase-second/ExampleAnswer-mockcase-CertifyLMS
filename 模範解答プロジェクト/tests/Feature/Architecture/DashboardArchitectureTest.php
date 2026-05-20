<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class DashboardArchitectureTest extends TestCase
{
    public function test_no_dashboard_specific_service_is_created(): void
    {
        $matches = glob(base_path('app/Services/Dashboard*Service.php')) ?: [];
        $this->assertEmpty($matches, 'dashboard 専用 Service の新設は禁止です: '.implode(', ', $matches));
    }

    public function test_no_dashboard_specific_policy_is_created(): void
    {
        $matches = glob(base_path('app/Policies/Dashboard*.php')) ?: [];
        $this->assertEmpty($matches, 'dashboard 専用 Policy の新設は禁止です: '.implode(', ', $matches));
    }

    public function test_no_dashboard_specific_middleware_is_created(): void
    {
        $matches = glob(base_path('app/Http/Middleware/Dashboard*.php')) ?: [];
        $this->assertEmpty($matches, 'dashboard 専用 Middleware の新設は禁止です: '.implode(', ', $matches));
    }

    public function test_enrollment_model_does_not_define_last_learning_session_relation(): void
    {
        $contents = file_get_contents(base_path('app/Models/Enrollment.php'));
        $this->assertStringNotContainsString('lastLearningSession', $contents, 'Enrollment.lastLearningSession リレーションは禁止(Action 側で withMax を使う)');
    }

    public function test_dashboard_actions_do_not_use_cache_facade(): void
    {
        $actionDir = base_path('app/UseCases/Dashboard');
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($actionDir));
        foreach ($rii as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('Cache::', $contents, $file->getPathname().' で Cache facade を使用しないでください');
        }
    }

    public function test_dashboard_blade_views_do_not_call_db_facade_or_model_query(): void
    {
        $viewDir = base_path('resources/views/dashboard');
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewDir));
        foreach ($rii as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('DB::', $contents, $file->getPathname().' で DB facade を使用しないでください');
            $this->assertDoesNotMatchRegularExpression('/\\\\App\\\\Models\\\\[A-Za-z]+::query/', $contents, $file->getPathname().' で Model::query を直接呼ばないでください');
        }
    }
}
