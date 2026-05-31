<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '処理中',
            self::Succeeded => '完了',
            self::Failed => '失敗',
            self::Refunded => '返金',
        };
    }
}
