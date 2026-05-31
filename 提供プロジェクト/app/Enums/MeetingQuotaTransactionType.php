<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingQuotaTransactionType: string
{
    case GrantedInitial = 'granted_initial';
    case Purchased = 'purchased';
    case Consumed = 'consumed';
    case Refunded = 'refunded';
    case AdminGrant = 'admin_grant';

    public function label(): string
    {
        return match ($this) {
            self::GrantedInitial => '初期付与',
            self::Purchased => '購入',
            self::Consumed => '消費',
            self::Refunded => '返却',
            self::AdminGrant => '管理者付与',
        };
    }
}
