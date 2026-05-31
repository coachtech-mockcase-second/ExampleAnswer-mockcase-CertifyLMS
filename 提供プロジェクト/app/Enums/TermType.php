<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enrollment の学習ターム(教習所メタファー)を表す Enum。
 *
 * - BasicLearning: 基礎ターム(初期値、教材を中心に学習)
 * - MockPractice: 実践ターム(初回模試セッション開始で自動切替)
 *
 * 遷移判定は TermJudgementService::recalculate(Enrollment) に集約され、MockExamSession 状態変化時に呼び出される。
 */
enum TermType: string
{
    case BasicLearning = 'basic_learning';
    case MockPractice = 'mock_practice';

    public function label(): string
    {
        return match ($this) {
            self::BasicLearning => '基礎ターム',
            self::MockPractice => '実践ターム',
        };
    }
}
