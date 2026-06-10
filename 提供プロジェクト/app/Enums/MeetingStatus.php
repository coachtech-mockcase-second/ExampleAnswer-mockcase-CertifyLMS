<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 1on1 面談予約 (Meeting) の状態を表す Enum。
 *
 * 状態遷移は受講生の予約 / 当事者キャンセル / 自動完了の 3 経路で構成される。
 *
 * - [*] → Reserved: 受講生の予約確定(時刻選択 → 自動コーチ割当 → 面談回数 -1 消費)
 * - Reserved → Canceled: 当事者キャンセル(scheduled_at まで + 面談回数 +1 返却)
 * - Reserved → Completed: AutoCompleteMeetingAction(scheduled_at + 60 分経過で Schedule Command が遷移)
 *
 * 終端: Canceled / Completed (再開や delete からの復帰はない)。
 */
enum MeetingStatus: string
{
    case Reserved = 'reserved';
    case Canceled = 'canceled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => '予約済',
            self::Canceled => 'キャンセル',
            self::Completed => '完了',
        };
    }

    /**
     * `<x-badge>` 等で利用するカラートークン名。
     */
    public function color(): string
    {
        return match ($this) {
            self::Reserved => 'info',
            self::Canceled => 'gray',
            self::Completed => 'success',
        };
    }
}
