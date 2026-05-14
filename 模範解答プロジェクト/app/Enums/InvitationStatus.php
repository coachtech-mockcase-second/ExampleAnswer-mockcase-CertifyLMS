<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '保留中',
            self::Accepted => '受領済',
            self::Expired => '期限切れ',
            self::Revoked => '取消済',
        };
    }
}
