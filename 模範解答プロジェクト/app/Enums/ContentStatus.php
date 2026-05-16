<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '下書き',
            self::Published => '公開中',
        };
    }
}
