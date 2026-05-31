<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MockExamSession の状態を表す Enum。
 *
 * 状態遷移: NotStarted → InProgress → Submitted → Graded (SubmitAction 内で同 transaction)
 *           NotStarted → Canceled (DestroyAction)
 * 終端: Graded / Canceled
 */
enum MockExamSessionStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => '未開始',
            self::InProgress => '受験中',
            self::Submitted => '提出済',
            self::Graded => '採点完了',
            self::Canceled => 'キャンセル',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::InProgress => 'info',
            self::Submitted => 'warning',
            self::Graded => 'success',
            self::Canceled => 'danger',
        };
    }
}
