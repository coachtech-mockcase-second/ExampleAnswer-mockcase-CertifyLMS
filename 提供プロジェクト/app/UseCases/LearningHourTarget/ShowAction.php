<?php

declare(strict_types=1);

namespace App\UseCases\LearningHourTarget;

use App\Models\Enrollment;
use App\Services\LearningHourTargetService;

/**
 * 学習時間目標サマリ画面 (/learning/enrollments/{enrollment}/hour-target) のデータを準備する Action。
 * 未設定 (LearningHourTarget 行なし) の Enrollment でも累計学習時間サマリを返せるよう Service に委譲。
 */
final class ShowAction
{
    public function __construct(
        private readonly LearningHourTargetService $service,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(Enrollment $enrollment): array
    {
        return [
            'enrollment' => $enrollment->loadMissing(['certification', 'learningHourTarget']),
            'summary' => $this->service->compute($enrollment),
        ];
    }
}
