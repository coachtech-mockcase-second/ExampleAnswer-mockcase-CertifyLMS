<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use App\UseCases\Plan\GraduateUserAction;
use Illuminate\Console\Command;

/**
 * プラン期間満了による自動卒業 Schedule Command。日次 00:45 起動。
 *
 * - in_progress + plan_expires_at < now() を抽出
 * - GraduateUserAction で User.status = graduated に遷移 + UserStatusLog / UserPlanLog 記録
 *
 * 招待期限切れ Schedule Command（invitations:expire、00:30）とロック競合しないよう
 * 開始時刻をずらし、withoutOverlapping(5) で多重起動も防ぐ。
 */
class GraduateExpiredUsersCommand extends Command
{
    protected $signature = 'users:graduate-expired';

    protected $description = 'プラン期間満了のユーザーを graduated に自動遷移する。';

    public function handle(GraduateUserAction $action): int
    {
        $users = User::query()
            ->with('plan')
            ->where('status', UserStatus::InProgress->value)
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->get();

        foreach ($users as $user) {
            $action($user);
        }

        $count = $users->count();
        $this->info("Graduated {$count} expired users.");

        return self::SUCCESS;
    }
}
