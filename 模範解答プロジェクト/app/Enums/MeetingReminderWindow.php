<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 面談リマインダ通知の発火タイミングを表す Enum。
 *
 * Eve: 前日 18:00 起動の dailyAt スケジュールで翌日分の Meeting に対して配信
 * OneHourBefore: everyFiveMinutes 起動で +55..65min 範囲の Meeting に対して配信
 */
enum MeetingReminderWindow: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';

    public function label(): string
    {
        return match ($this) {
            self::Eve => '前日リマインド',
            self::OneHourBefore => '1時間前リマインド',
        };
    }
}
