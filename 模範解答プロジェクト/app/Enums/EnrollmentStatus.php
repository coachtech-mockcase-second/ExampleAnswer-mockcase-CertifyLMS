<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 受講登録（Enrollment）の状態を表す Enum。学習中 / 一時停止 / 修了 / 不合格の 4 値。
 * 資格カタログの受講中タブ判定と、`Certificate\IssueAction` の修了証発行ガードから参照される。
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
