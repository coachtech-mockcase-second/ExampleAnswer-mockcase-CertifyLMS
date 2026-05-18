<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 合格可能性のバンドを表す Enum。
 *
 * 直近 3 件の Graded MockExamSession の平均得点率を、当該模試の合格点(passing_score_snapshot) を基準に
 * 0.90 倍 / 0.70 倍で 3 つのバンドに分け、採点済セッションが 0 件の場合は Unknown を返す。
 */
enum PassProbabilityBand: string
{
    case Safe = 'safe';
    case Warning = 'warning';
    case Danger = 'danger';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Safe => '合格圏',
            self::Warning => '注意',
            self::Danger => '要対策',
            self::Unknown => '判定不可',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Safe => 'success',
            self::Warning => 'warning',
            self::Danger => 'danger',
            self::Unknown => 'gray',
        };
    }
}
