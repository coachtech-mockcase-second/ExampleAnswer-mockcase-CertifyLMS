<?php

declare(strict_types=1);

namespace App\Enums;

enum UserPlanLogEventType: string
{
    case Assigned = 'assigned';
    case Renewed = 'renewed';
    case Canceled = 'canceled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'プラン割当',
            self::Renewed => 'プラン延長',
            self::Canceled => 'プランキャンセル',
            self::Expired => '期限満了',
        };
    }
}
