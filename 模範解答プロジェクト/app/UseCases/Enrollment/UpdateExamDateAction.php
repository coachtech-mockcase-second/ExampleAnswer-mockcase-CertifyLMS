<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\EnrollmentAlreadyPassedException;
use App\Models\Enrollment;

/**
 * admin による Enrollment.exam_date の単独更新 Action。
 *
 * 修了済(passed) の Enrollment は変更禁止(409)。status / current_term / passed_at は本 Action では UPDATE しない
 * (各々の専用 Action 経由のみ)。EnrollmentStatusLog にも記録しない(状態遷移ではない)。
 */
final class UpdateExamDateAction
{
    /**
     * @param array{exam_date?: ?string} $validated
     *
     * @throws EnrollmentAlreadyPassedException
     */
    public function __invoke(Enrollment $enrollment, array $validated): Enrollment
    {
        if ($enrollment->status === EnrollmentStatus::Passed) {
            throw new EnrollmentAlreadyPassedException;
        }

        $enrollment->update([
            'exam_date' => $validated['exam_date'] ?? null,
        ]);

        return $enrollment->refresh();
    }
}
