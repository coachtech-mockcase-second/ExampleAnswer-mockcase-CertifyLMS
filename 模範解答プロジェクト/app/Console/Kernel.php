<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 目標受験日超過の learning Enrollment を failed に自動遷移(他バッチと時刻被りなしで先頭起動)
        $schedule->command('enrollments:fail-expired')->dailyAt('00:00')->withoutOverlapping(5);

        // 期限切れ Invitation の cascade 処理
        $schedule->command('invitations:expire')->dailyAt('00:30')->withoutOverlapping(5);

        // プラン期間満了による自動 graduated 遷移（invitations:expire とロック競合しないよう 00:45 にずらす）
        $schedule->command('users:graduate-expired')->dailyAt('00:45')->withoutOverlapping(5);

        // 滞留 open 学習セッションを max_session_seconds で強制クローズ(ブラウザ閉じ / PC スリープ等の保険)
        $schedule->command('learning:close-stale-sessions')->dailyAt('01:00')->withoutOverlapping(5);

        // 終了時刻超過の reserved 面談を completed に自動遷移(15 分間隔でリアルタイム性確保)
        $schedule->command('meetings:auto-complete')->cron('*/15 * * * *')->withoutOverlapping(5);

        // 翌日 reserved 面談への前日リマインダ通知配信(18:00 起動)
        $schedule->command('notifications:send-meeting-reminders --window=eve')
            ->dailyAt('18:00')
            ->withoutOverlapping(5);

        // 開始 1 時間前 reserved 面談へのリマインダ通知配信(5 分間隔で +55..65min 範囲を巡回)
        $schedule->command('notifications:send-meeting-reminders --window=one_hour_before')
            ->everyFiveMinutes()
            ->withoutOverlapping(5);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
