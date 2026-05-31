<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Enums\InvitationStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Services\UserStatusChangeService;
use App\Services\UserWithdrawalService;
use Illuminate\Support\Facades\DB;

/**
 * 期限切れ pending Invitation を一括 expired にし、紐付く invited User を cascade withdraw するユースケース。
 *
 * Schedule Command(`invitations:expire`) から日次で呼ばれることを想定し、actor=null(システム自動)で
 * UserStatusLog に記録する。同一トランザクション内で Invitation status 更新 + User の cascade withdraw +
 * UserStatusLog 記録を原子的に実施。
 */
final class ExpireInvitationsAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly UserWithdrawalService $withdrawalService,
    ) {}

    /**
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
                    // record() は遷移前 status を参照するため、必ず WithdrawalService より前に呼ぶ
                    $this->statusChanger->record(
                        $user,
                        UserStatus::Withdrawn,
                        null,
                        '招待期限切れ',
                    );
                    $this->withdrawalService->withdraw($user);
                }

                $count++;
            }

            return $count;
        });
    }
}
