<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\CompletionNotEligibleException;
use App\Exceptions\Enrollment\EnrollmentNotLearningException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CompletionEligibilityService;
use App\Services\EnrollmentStatusChangeService;
use App\UseCases\Certificate\IssueAction as IssueCertificateAction;
use App\UseCases\Notification\NotifyCompletionApprovedAction;
use Illuminate\Support\Facades\DB;

/**
 * 受講生本人による「修了証を受け取る」自己発火 Action。
 *
 * Controller の `$this->authorize('receiveCertificate', $enrollment)` で本人 + status==Learning を判定済の前提。
 * 本 Action はデータ整合性(`CompletionEligibilityService` の合格判定)のみ実施し、認可は Policy 側に委譲する。
 *
 * 処理フロー(すべて DB::transaction() 内):
 * 1. CompletionEligibilityService::isEligible でガード(false なら CompletionNotEligibleException 409)
 * 2. Enrollment を status=passed / passed_at=now() に更新
 * 3. EnrollmentStatusLog 記録(from=learning / to=passed / changed_by=本人 / reason='受講生による修了証受領')
 * 4. IssueCertificateAction を呼んで Certificate 発行 + PDF 生成
 * 5. commit 後に修了通知 (Database / Mail / Broadcast) を本人へ発火 (Mail には PDF DL URL を含める)
 */
final class ReceiveCertificateAction
{
    public function __construct(
        private readonly CompletionEligibilityService $eligibility,
        private readonly EnrollmentStatusChangeService $statusChanger,
        private readonly IssueCertificateAction $issueCertificate,
        private readonly NotifyCompletionApprovedAction $notifyCompletion,
    ) {}

    /**
     * @throws CompletionNotEligibleException 公開模試すべてに合格していない
     * @throws EnrollmentNotLearningException Policy の事前判定漏れに対する整合性ガード
     */
    public function __invoke(Enrollment $enrollment): Certificate
    {
        if ($enrollment->status !== EnrollmentStatus::Learning) {
            throw new EnrollmentNotLearningException;
        }

        if (! $this->eligibility->isEligible($enrollment)) {
            throw new CompletionNotEligibleException;
        }

        return DB::transaction(function () use ($enrollment) {
            $enrollment->update([
                'status' => EnrollmentStatus::Passed->value,
                'passed_at' => now(),
            ]);

            $this->statusChanger->recordStatusChange(
                $enrollment,
                fromStatus: EnrollmentStatus::Learning,
                toStatus: EnrollmentStatus::Passed,
                changedBy: $enrollment->user,
                reason: '受講生による修了証受領',
            );

            $certificate = ($this->issueCertificate)($enrollment->refresh());

            DB::afterCommit(function () use ($enrollment, $certificate): void {
                ($this->notifyCompletion)($enrollment->refresh(), $certificate);
            });

            return $certificate;
        });
    }
}
