<?php

declare(strict_types=1);

namespace App\UseCases\AdminAnnouncement;

use App\Enums\AdminAnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Notification\AdminAnnouncementInvalidTargetException;
use App\Exceptions\Notification\AdminAnnouncementTargetNotFoundException;
use App\Models\AdminAnnouncement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Notification\NotifyAdminAnnouncementAction;
use Illuminate\Support\Facades\DB;

/**
 * 管理者お知らせの配信 Action。
 *
 * 処理:
 * 1. target_type と target_certification_id / target_user_id の整合性検査 (不整合は 422)
 * 2. target_certification / target_user の存在検査 (見つからなければ 404)
 * 3. DB::transaction 内で AdminAnnouncement INSERT + 対象受講生の解決 + dispatched_count / dispatched_at の確定
 * 4. commit 後に DB::afterCommit で各受講生へ通知発火 (Mail / Broadcast 副作用が rollback 漏れすることを防ぐ)
 *
 * @throws AdminAnnouncementInvalidTargetException target_type と FK 列の組み合わせ不整合
 * @throws AdminAnnouncementTargetNotFoundException 指定された資格 / ユーザーが存在しない
 */
final class StoreAction
{
    public function __construct(
        private readonly NotifyAdminAnnouncementAction $notify,
    ) {}

    /**
     * @param array{title: string, body: string, target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     */
    public function __invoke(User $admin, array $validated): AdminAnnouncement
    {
        $targetType = AdminAnnouncementTargetType::from($validated['target_type']);
        $targetCertificationId = $validated['target_certification_id'] ?? null;
        $targetUserId = $validated['target_user_id'] ?? null;

        $this->guardTargetConsistency($targetType, $targetCertificationId, $targetUserId);

        return DB::transaction(function () use ($admin, $validated, $targetType, $targetCertificationId, $targetUserId) {
            $announcement = AdminAnnouncement::create([
                'created_by_user_id' => $admin->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType->value,
                'target_certification_id' => $targetCertificationId,
                'target_user_id' => $targetUserId,
                'dispatched_count' => 0,
                'dispatched_at' => null,
            ]);

            $recipients = $this->resolveRecipients($announcement);
            $announcement->update([
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => now(),
            ]);

            DB::afterCommit(function () use ($announcement, $recipients): void {
                foreach ($recipients as $recipient) {
                    ($this->notify)($announcement, $recipient);
                }
            });

            return $announcement->fresh();
        });
    }

    private function guardTargetConsistency(
        AdminAnnouncementTargetType $type,
        ?string $certificationId,
        ?string $userId,
    ): void {
        $valid = match ($type) {
            AdminAnnouncementTargetType::AllStudents => $certificationId === null && $userId === null,
            AdminAnnouncementTargetType::Certification => $certificationId !== null && $userId === null,
            AdminAnnouncementTargetType::User => $userId !== null && $certificationId === null,
        };

        if (! $valid) {
            throw new AdminAnnouncementInvalidTargetException();
        }

        if ($type === AdminAnnouncementTargetType::Certification && ! Certification::where('id', $certificationId)->exists()) {
            throw new AdminAnnouncementTargetNotFoundException();
        }
        if ($type === AdminAnnouncementTargetType::User && ! User::where('id', $userId)->where('role', UserRole::Student)->exists()) {
            throw new AdminAnnouncementTargetNotFoundException();
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveRecipients(AdminAnnouncement $announcement): \Illuminate\Support\Collection
    {
        return match ($announcement->target_type) {
            AdminAnnouncementTargetType::AllStudents => User::query()
                ->where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->get(),
            AdminAnnouncementTargetType::Certification => User::query()
                ->where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->whereHas('enrollments', function ($q) use ($announcement) {
                    $q->where('certification_id', $announcement->target_certification_id)
                        ->where('status', EnrollmentStatus::Learning);
                })
                ->get(),
            AdminAnnouncementTargetType::User => User::query()
                ->where('id', $announcement->target_user_id)
                ->where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->get(),
        };
    }
}
