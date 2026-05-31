<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\CertificationStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
use App\Exceptions\Enrollment\EnrollmentAlreadyEnrolledException;
use App\Models\Certification;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\ChatMemberSyncService;
use App\Services\DefaultEnrollmentService;
use App\Services\EnrollmentStatusChangeService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 受講生による自己受講登録 Action。
 *
 * - 対象資格が published 以外 / SoftDelete 済の場合は 404
 * - 同一 user × certification の Enrollment が active(非 SoftDelete) で存在する場合は 409
 * - 初期値: status=learning / current_term=basic_learning / passed_at=null
 * - 直後に EnrollmentStatusLog(from_status=null / to_status=learning / changed_reason='新規登録') を 1 件記録
 * - 初回登録時(受講生の default_enrollment_id が NULL)は自動的に当該 Enrollment をデフォルトに設定
 *
 * 担当コーチの自動設定は行わない(資格 × N コーチ N:N、certification_coach_assignments で資格経由)。
 *
 * @param-out  Enrollment 作成された Enrollment
 */
final class StoreAction
{
    public function __construct(
        private readonly EnrollmentStatusChangeService $statusChanger,
        private readonly DefaultEnrollmentService $defaultEnrollmentService,
        private readonly ChatMemberSyncService $chatMemberSync,
    ) {}

    /**
     * @param array{certification_id: string, exam_date?: ?string} $validated
     *
     * @throws EnrollmentAlreadyEnrolledException
     */
    public function __invoke(User $student, array $validated): Enrollment
    {
        $certification = Certification::query()
            ->where('id', $validated['certification_id'])
            ->where('status', CertificationStatus::Published->value)
            ->first();

        if ($certification === null) {
            throw new NotFoundHttpException('指定された資格は受講できません。');
        }

        return DB::transaction(function () use ($student, $certification, $validated) {
            $duplicated = Enrollment::query()
                ->where('user_id', $student->id)
                ->where('certification_id', $certification->id)
                ->exists();

            if ($duplicated) {
                throw new EnrollmentAlreadyEnrolledException;
            }

            $enrollment = Enrollment::create([
                'user_id' => $student->id,
                'certification_id' => $certification->id,
                'exam_date' => $validated['exam_date'] ?? null,
                'status' => EnrollmentStatus::Learning->value,
                'current_term' => TermType::BasicLearning->value,
                'passed_at' => null,
            ]);

            $this->statusChanger->recordStatusChange(
                $enrollment,
                fromStatus: null,
                toStatus: EnrollmentStatus::Learning,
                changedBy: $student,
                reason: '新規登録',
            );

            $this->defaultEnrollmentService->resolveAfterCreate($student, $enrollment);

            $chatRoom = ChatRoom::create([
                'enrollment_id' => $enrollment->id,
                'last_message_at' => null,
            ]);
            $chatRoom->setRelation('enrollment', $enrollment->setRelation('certification', $certification));
            $this->chatMemberSync->syncForRoom($chatRoom);

            return $enrollment;
        });
    }
}
