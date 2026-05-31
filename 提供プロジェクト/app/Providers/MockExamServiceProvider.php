<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Contracts\WeaknessAnalysisServiceContract;
use App\Services\WeaknessAnalysisService;
use Illuminate\Support\ServiceProvider;

/**
 * 模試 Feature の依存登録を担う ServiceProvider。
 *
 * quiz-answering Feature が定義する WeaknessAnalysisServiceContract に対する正規実装として
 * WeaknessAnalysisService を bind する。`bind()` を使うので、QuizAnsweringServiceProvider が
 * 先に NullWeaknessAnalysisService を bindIf 登録していても上書きされる。
 *
 * config/app.php の providers では QuizAnsweringServiceProvider より後に登録すること。
 */
final class MockExamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WeaknessAnalysisServiceContract::class, WeaknessAnalysisService::class);
    }
}
