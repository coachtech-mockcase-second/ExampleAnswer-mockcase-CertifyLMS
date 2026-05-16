<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Services\UserStatusChangeService;
use App\Services\UserWithdrawalService;
use Illuminate\Support\Facades\DB;

class ExpireInvitationsAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly UserWithdrawalService $withdrawalService,
    ) {}

    /**
     * 期限切れ pending Invitation を一括 expired にし、紐付く invited User を cascade withdraw する。
     * Schedule Command（invitations:expire）から呼ばれることを想定し、actor=null でシステム自動相当として UserStatusLog 記録。
     *
     * @return int 処理された Invitation の件数
     */
    public function __invoke(): int
    {
        return DB::transaction(function () {
            $expiring = Invitation::expired()->get();
            $count = 0;

            foreach ($expiring as $invitation) {
                $invitation->forceFill(['status' => InvitationStatus::Expired])->save();

                $user = $invitation->user;
                if ($user !== null && $user->status === UserStatus::Invited) {
                    $this->withdrawalService->withdraw($user);
                    $this->statusChanger->record(
                        $user,
                        UserStatus::Withdrawn,
                        null,
                        '招待期限切れ',
                    );
                }

                $count++;
            }

            return $count;
        });
    }
}
