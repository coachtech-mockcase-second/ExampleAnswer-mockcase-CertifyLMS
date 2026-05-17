<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\EnrollmentInvalidTransitionException;
use App\Models\Enrollment;

/**
 * 受講生による受講解除(SoftDelete) Action。learning 状態の Enrollment のみ削除可。
 * passed / failed は履歴として残すため拒否する。
 */
final class DestroyAction
{
    /**
     * @throws EnrollmentInvalidTransitionException
     */
    public function __invoke(Enrollment $enrollment): void
    {
        if ($enrollment->status !== EnrollmentStatus::Learning) {
            throw EnrollmentInvalidTransitionException::forDestroy();
        }

        $enrollment->delete();
    }
}
