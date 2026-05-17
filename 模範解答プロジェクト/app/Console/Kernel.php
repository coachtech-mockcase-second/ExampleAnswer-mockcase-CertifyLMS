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
        // 期限切れ Invitation の cascade 処理
        $schedule->command('invitations:expire')->dailyAt('00:30')->withoutOverlapping(5);

        // プラン期間満了による自動 graduated 遷移（invitations:expire とロック競合しないよう 00:45 にずらす）
        $schedule->command('users:graduate-expired')->dailyAt('00:45')->withoutOverlapping(5);
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
