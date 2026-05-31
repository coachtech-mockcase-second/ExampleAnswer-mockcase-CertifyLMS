<?php

declare(strict_types=1);

namespace App\UseCases\LearningSession;

use App\Models\LearningSession;
use App\Models\Section;
use App\Models\User;
use App\Services\SessionCloseService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * 学習セッションのサーバ側 auto-start を行う Action。BrowseController::showSection から呼ばれ、
 * JS / 公開 HTTP エンドポイント経由ではない。
 *
 * Enrollment 状態 (`learning` / `passed` 許容、`failed` 拒否) の判定は呼出元の SectionViewPolicy 側で
 * 完了している前提。本 Action は閲覧可能と判定された Section に対するセッション生成のみを担う。
 *
 * - 同一 user の open session があれば `SessionCloseService::closeOpenSessions(asAutoClosed: true)` で先に閉じる
 *   (別 Section 遷移時の自動切替動作)
 * - 新規 LearningSession を INSERT し、user_id は enrollment.user_id から denormalize して保持
 */
final class StartAction
{
    public function __construct(
        private readonly SessionCloseService $sessionCloseService,
    ) {}

    public function __invoke(User $student, Section $section): LearningSession
    {
        $section->loadMissing('chapter.part');
        $part = $section->chapter?->part;

        if ($part === null) {
            throw new AccessDeniedHttpException('対象の Section にアクセスできません。');
        }

        $enrollment = $student->enrollments()
            ->where('certification_id', $part->certification_id)
            ->first();

        if ($enrollment === null) {
            throw new AccessDeniedHttpException('対象の資格に受講登録していません。');
        }

        return DB::transaction(function () use ($student, $section, $enrollment) {
            $this->sessionCloseService->closeOpenSessions($student, asAutoClosed: true);

            return LearningSession::create([
                'user_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'section_id' => $section->id,
                'started_at' => now(),
            ]);
        });
    }
}
