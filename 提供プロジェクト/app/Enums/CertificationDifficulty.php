<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 資格の難易度を表す Enum。初級 / 中級 / 上級 の 3 値。
 * 受講生カタログのフィルタと admin 管理画面の入力プルダウンから参照される。
 */
enum CertificationDifficulty: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => '初級',
            self::Intermediate => '中級',
            self::Advanced => '上級',
        };
    }
}
