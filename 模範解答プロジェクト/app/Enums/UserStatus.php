<?php

namespace App\Enums;

enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Invited => '招待中',
            self::Active => 'アクティブ',
            self::Withdrawn => '退会済',
        };
    }
}
