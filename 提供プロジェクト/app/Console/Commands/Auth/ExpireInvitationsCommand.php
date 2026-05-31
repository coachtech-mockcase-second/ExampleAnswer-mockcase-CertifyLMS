<?php

declare(strict_types=1);

namespace App\Console\Commands\Auth;

use App\UseCases\Auth\ExpireInvitationsAction;
use Illuminate\Console\Command;

class ExpireInvitationsCommand extends Command
{
    protected $signature = 'invitations:expire';

    protected $description = '期限切れの pending Invitation を一括 expired にし、紐付く invited User を cascade withdraw する';

    public function handle(ExpireInvitationsAction $action): int
    {
        $count = $action();

        $this->info("期限切れ Invitation を {$count} 件処理しました。");

        return self::SUCCESS;
    }
}
