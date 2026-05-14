<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Coach = 'coach';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => '管理者',
            self::Coach => 'コーチ',
            self::Student => '受講生',
        };
    }
}
