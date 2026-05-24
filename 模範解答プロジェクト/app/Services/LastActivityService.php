<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\Api\EnrollmentController;
use App\Models\Enrollment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment ごとの「直近活動日時」を集計する Service。
 *
 * LearningSession.ended_at(教材セッションの終了) と SectionQuestionAnswer.answered_at(演習解答)
 * の最大値を統合し、受講生が最後に学習行動を起こした日時を返す。
 *
 * 受講生ダッシュボード / コーチダッシュボード / 運用エクスポート API で利用される。
 * 単体取得 (`lastActivityFor`) と N+1 回避用バッチ取得 (`batchLastActivityFor`) の 2 系統を提供する。
 *
 * @see EnrollmentController::index()
 */
final class LastActivityService
{
    /**
     * 単一 Enrollment の最終活動日時を返す。
     */
    public function lastActivityFor(Enrollment $enrollment): ?Carbon
    {
        $batch = $this->batchLastActivityFor(new Collection([$enrollment]));

        return $batch[$enrollment->id] ?? null;
    }

    /**
     * 複数 Enrollment の最終活動日時を一括取得する (N+1 回避)。
     *
     * 戻り値は Enrollment.id をキー、最終活動 Carbon を値とする。
     * 活動履歴が無い Enrollment はキー自体を含めない。
     *
     * @param Collection<int, Enrollment> $enrollments
     *
     * @return array<string, Carbon>
     */
    public function batchLastActivityFor(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }

        $enrollmentIds = $enrollments->pluck('id')->all();

        $sessionMax = DB::table('learning_sessions')
            ->whereIn('enrollment_id', $enrollmentIds)
            ->whereNotNull('ended_at')
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, MAX(ended_at) AS last_at')
            ->pluck('last_at', 'enrollment_id');

        $answerRows = DB::table('section_question_answers AS sqa')
            ->join('section_questions AS sq', 'sq.id', '=', 'sqa.section_question_id')
            ->join('sections AS s', 's.id', '=', 'sq.section_id')
            ->join('chapters AS c', 'c.id', '=', 's.chapter_id')
            ->join('parts AS p', 'p.id', '=', 'c.part_id')
            ->join('enrollments AS e', function ($join) {
                $join->on('e.user_id', '=', 'sqa.user_id')
                    ->on('e.certification_id', '=', 'p.certification_id');
            })
            ->whereIn('e.id', $enrollmentIds)
            ->groupBy('e.id')
            ->selectRaw('e.id AS enrollment_id, MAX(sqa.answered_at) AS last_at')
            ->pluck('last_at', 'enrollment_id');

        $result = [];
        foreach ($enrollmentIds as $id) {
            $candidates = array_filter([
                $sessionMax[$id] ?? null,
                $answerRows[$id] ?? null,
            ]);

            if ($candidates === []) {
                continue;
            }

            $latest = max(array_map(fn (string $ts) => Carbon::parse($ts), $candidates));
            $result[$id] = $latest;
        }

        return $result;
    }
}
