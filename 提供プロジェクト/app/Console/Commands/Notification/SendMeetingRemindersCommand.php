<?php

declare(strict_types=1);

namespace App\Console\Commands\Notification;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Notification\NotifyMeetingReminderAction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * 予約済の面談に対して受講生 + 担当コーチ双方へリマインダ通知を配信する Schedule Command。
 *
 * 起動モード:
 * - `--window=eve`: 「前日 18:00」の dailyAt で起動。翌日 (now の翌日 00:00..23:59) の Reserved Meeting を対象
 * - `--window=one_hour_before`: 5 分間隔で起動。`now + 55..65min` の範囲に scheduled_at がある Reserved Meeting を対象
 *
 * 同一 `(meeting_id, window)` の重複は `NotifyMeetingReminderAction` 側で JSON path クエリにより排除する。
 * `withoutOverlapping` を Kernel 側で付けて Schedule の二重起動でも安全に動作する。
 */
class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window=eve : 配信タイミング(eve=前日 / one_hour_before=1時間前)}';

    protected $description = '予約済の面談に対して、前日 / 1 時間前のリマインダ通知を配信する。';

    public function handle(NotifyMeetingReminderAction $action): int
    {
        $windowInput = (string) $this->option('window');
        $window = MeetingReminderWindow::tryFrom($windowInput);

        if ($window === null) {
            $this->error("不正な --window 値: {$windowInput} (eve | one_hour_before のいずれかを指定してください)");

            return self::INVALID;
        }

        $query = $this->buildTargetQuery($window);

        $count = 0;
        $query->orderBy('id')
            ->chunkById(100, function ($meetings) use ($action, $window, &$count): void {
                foreach ($meetings as $meeting) {
                    $action($meeting, $window);
                    $count++;
                }
            });

        $this->info("リマインダ通知 ({$window->label()}) を {$count} 件の面談に対して処理しました。");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Meeting>
     */
    private function buildTargetQuery(MeetingReminderWindow $window): Builder
    {
        $query = Meeting::query()
            ->where('status', MeetingStatus::Reserved->value);

        return match ($window) {
            MeetingReminderWindow::Eve => $query
                ->where('scheduled_at', '>=', now()->addDay()->startOfDay())
                ->where('scheduled_at', '<=', now()->addDay()->endOfDay()),
            MeetingReminderWindow::OneHourBefore => $query
                ->where('scheduled_at', '>=', now()->addMinutes(55))
                ->where('scheduled_at', '<=', now()->addMinutes(65)),
        };
    }
}
