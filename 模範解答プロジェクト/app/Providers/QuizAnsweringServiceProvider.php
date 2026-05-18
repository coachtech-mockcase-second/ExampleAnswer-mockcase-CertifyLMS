<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\WeaknessAnalysisServiceContract;
use App\Services\NullWeaknessAnalysisService;
use Illuminate\Support\ServiceProvider;

/**
 * Section 紐づき問題演習 / 苦手分野ドリル機能の依存登録を担う ServiceProvider。
 *
 * WeaknessAnalysisServiceContract に対し、config('quiz-answering.weakness_analysis_service') で
 * 指定された実装クラス(模試 Feature 側で提供)、または NullWeaknessAnalysisService を
 * bindIf で登録する。すでに別 Provider がバインド済みの場合は何もしない。
 */
final class QuizAnsweringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $configured = config('quiz-answering.weakness_analysis_service');

        if (is_string($configured) && class_exists($configured)) {
            $this->app->bindIf(WeaknessAnalysisServiceContract::class, $configured);

            return;
        }

        $this->app->bindIf(WeaknessAnalysisServiceContract::class, NullWeaknessAnalysisService::class);
    }
}
