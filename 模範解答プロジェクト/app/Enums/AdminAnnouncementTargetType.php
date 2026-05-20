<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 管理者お知らせの配信対象を表す Enum。
 * 配信時の対象 User Collection 解決クエリを分岐させる。
 */
enum AdminAnnouncementTargetType: string
{
    case AllStudents = 'all_students';
    case Certification = 'certification';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::AllStudents => '全受講生',
            self::Certification => '資格指定',
            self::User => 'ユーザー指定',
        };
    }
}
