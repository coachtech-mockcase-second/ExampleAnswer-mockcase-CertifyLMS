<?php

declare(strict_types=1);

namespace App\Console\Commands\Learning;

use App\UseCases\LearningSession\CloseStaleSessionsAction;
use Illuminate\Console\Command;

/**
 * 滞留 open 学習セッションの強制クローズを実行する Schedule Command。
 *
 * 起動: 日次 00:30 (App\Console\Kernel::schedule で登録、withoutOverlapping(5) で多重起動防止)。
 * 処理: started_at < now() - max_session_seconds の open セッションを auto_closed=true で一括 close。
 * 目的: ブラウザ閉じ / PC スリープ / ネット断 等で別 Section auto-start が発火しなかった残骸の救済。
 */
final class CloseStaleSessionsCommand extends Command
{
    protected $signature = 'learning:close-stale-sessions';

    protected $description = '滞留している open 学習セッションを max_session_seconds に基づき強制クローズする';

    public function handle(CloseStaleSessionsAction $action): int
    {
        $count = $action();
        $this->info(sprintf('Closed %d stale learning sessions.', $count));

        return self::SUCCESS;
    }
}
