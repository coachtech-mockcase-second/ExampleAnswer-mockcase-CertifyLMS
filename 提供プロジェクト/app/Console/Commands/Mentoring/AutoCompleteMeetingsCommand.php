<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Meeting\AutoCompleteMeetingAction;
use Illuminate\Console\Command;

/**
 * `scheduled_at + 60 分` を過ぎた reserved 面談を completed に一括遷移する Schedule Command。
 *
 * 15 分間隔で起動し、終了時刻超過の予約を即時に履歴側へ送り出す(運用上のリアルタイム性確保)。
 * AutoCompleteMeetingAction が行レベルロック + 状態再確認で二重遷移を防ぐ(冪等)。
 */
class AutoCompleteMeetingsCommand extends Command
{
    protected $signature = 'meetings:auto-complete';

    protected $description = 'scheduled_at + 60 分を過ぎた予約済の面談を完了状態へ自動遷移する';

    public function handle(AutoCompleteMeetingAction $action): int
    {
        $count = 0;

        $meetings = Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->where('scheduled_at', '<', now()->subMinutes(60))
            ->get();

        foreach ($meetings as $meeting) {
            $action($meeting);
            $count++;
        }

        $this->info("自動完了した面談を {$count} 件処理しました。");

        return self::SUCCESS;
    }
}
