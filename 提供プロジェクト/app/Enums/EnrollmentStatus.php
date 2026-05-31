<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 受講登録(Enrollment)の状態を表す Enum。3 値モデル。
 *
 * - Learning: 受講中(初期値、教材閲覧・演習・模試・修了申請が可能)
 * - Passed: 修了(受講生「修了証を受け取る」自己発火 → 即時遷移、Certificate 発行済)
 * - Failed: 学習中止(admin の手動失敗マーク or 試験日超過自動失敗)
 *
 * paused(休止中) は採用しない(複数資格同時受講可モデルでは「他資格に集中」で代替可能)。
 */
enum EnrollmentStatus: string
{
    case Learning = 'learning';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Learning => '学習中',
            self::Passed => '修了',
            self::Failed => '学習中止',
        };
    }
}
