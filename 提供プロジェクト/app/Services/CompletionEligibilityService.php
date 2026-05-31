<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;

/**
 * Enrollment の修了可否を判定する Service。
 *
 * 判定ロジック: 対象資格に紐付く公開模試(is_published=true) の件数と、
 * 当該 Enrollment 配下の MockExamSession で pass=true かつ DISTINCT な mock_exam_id の件数が
 * 一致したとき eligible とする。公開模試が 1 件もない場合は常に false(取得すべき修了証がない)。
 *
 * 利用先: 受講生ダッシュボード(「修了証を受け取る」ボタンの活性判定) /
 * ReceiveCertificateAction(処理前の整合性ガード)
 *
 * `final` 不採用: 受講生「修了証を受け取る」E2E テストで Mockery で isEligible を強制 true / false 化したい
 * ケースがあるため。
 */
class CompletionEligibilityService
{
    public function isEligible(Enrollment $enrollment): bool
    {
        $publishedCount = MockExam::query()
            ->where('certification_id', $enrollment->certification_id)
            ->where('is_published', true)
            ->count();

        if ($publishedCount === 0) {
            return false;
        }

        $passedCount = MockExamSession::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('pass', true)
            ->distinct()
            ->count('mock_exam_id');

        return $passedCount === $publishedCount;
    }
}
