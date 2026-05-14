<?php

namespace App\Enums;

/**
 * [[enrollment]] Feature が将来正式所有する Enum。
 * [[certification-management]] Feature では `certification_catalog` の受講中タブ判定と
 * `Certificate\IssueAction` の Enrollment ガード判定で参照する。
 */
enum EnrollmentStatus: string
{
    case Learning = 'learning';
    case Paused = 'paused';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Learning => '学習中',
            self::Paused => '一時停止',
            self::Passed => '修了',
            self::Failed => '不合格',
        };
    }
}
