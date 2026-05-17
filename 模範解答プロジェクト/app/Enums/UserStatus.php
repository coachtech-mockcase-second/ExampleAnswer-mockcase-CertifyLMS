<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Invited = 'invited';
    case InProgress = 'in_progress';
    case Graduated = 'graduated';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Invited => '招待中',
            self::InProgress => '受講中',
            self::Graduated => '卒業',
            self::Withdrawn => '退会済',
        };
    }
}
