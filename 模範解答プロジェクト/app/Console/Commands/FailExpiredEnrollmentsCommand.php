<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Services\EnrollmentStatusChangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 目標受験日(exam_date) を過ぎても学習中の Enrollment を学習中止(failed) に自動遷移する Schedule Command。
 *
 * 日次 00:00 起動。exam_date IS NULL の Enrollment は対象外(目標受験日未設定は任意のため)。
 * 各遷移ごとに EnrollmentStatusLog(changed_by=null = システム自動 / reason='試験日超過による自動失敗') を記録。
 */
class FailExpiredEnrollmentsCommand extends Command
{
    protected $signature = 'enrollments:fail-expired';

    protected $description = '目標受験日を超過した learning Enrollment を failed に自動遷移する。';

    public function handle(EnrollmentStatusChangeService $statusChanger): int
    {
        $enrollments = Enrollment::query()
            ->where('status', EnrollmentStatus::Learning->value)
            ->whereNotNull('exam_date')
            ->whereDate('exam_date', '<', now()->toDateString())
            ->get();

        foreach ($enrollments as $enrollment) {
            DB::transaction(function () use ($enrollment, $statusChanger) {
                $enrollment->update(['status' => EnrollmentStatus::Failed->value]);

                $statusChanger->recordStatusChange(
                    $enrollment,
                    fromStatus: EnrollmentStatus::Learning,
                    toStatus: EnrollmentStatus::Failed,
                    changedBy: null,
                    reason: '試験日超過による自動失敗',
                );
            });
        }

        $count = $enrollments->count();
        $this->info("Failed {$count} expired enrollments.");

        return self::SUCCESS;
    }
}
