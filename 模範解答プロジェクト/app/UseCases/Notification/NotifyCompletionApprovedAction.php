<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserStatus;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Notifications\Enrollment\CompletionApprovedNotification;

/**
 * 修了証発行完了を受講生本人に配信するラッパー Action。
 *
 * 発火元は `\App\UseCases\Enrollment\ReceiveCertificateAction` (受講生「修了証を受け取る」操作で自己発火)。
 * 受講生本人の `status !== InProgress` は配信スキップする (graduated 状態への通知も対象外)。
 */
final class NotifyCompletionApprovedAction
{
    public function __invoke(Enrollment $enrollment, Certificate $certificate): void
    {
        $enrollment->loadMissing('user');
        $student = $enrollment->user;

        if ($student === null) {
            return;
        }
        if ($student->status !== UserStatus::InProgress) {
            return;
        }

        $student->notify(new CompletionApprovedNotification($enrollment, $certificate));
    }
}
