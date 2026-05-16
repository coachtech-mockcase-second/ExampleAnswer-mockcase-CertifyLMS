<?php

declare(strict_types=1);

namespace App\Enums;

enum CertificationDifficulty: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Expert = 'expert';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => '初級',
            self::Intermediate => '中級',
            self::Advanced => '上級',
            self::Expert => 'エキスパート',
        };
    }
}
