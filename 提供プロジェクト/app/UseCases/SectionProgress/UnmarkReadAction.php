<?php

declare(strict_types=1);

namespace App\UseCases\SectionProgress;

use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Section の読了マーク取消 (冪等 SoftDelete) を行う Action。
 * 既に削除済 or 未存在の場合は副作用なしで返す。所有 Enrollment が無い場合は 403 で弾く。
 */
final class UnmarkReadAction
{
    public function __invoke(User $student, Section $section): void
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

        DB::transaction(function () use ($enrollment, $section) {
            $progress = SectionProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('section_id', $section->id)
                ->lockForUpdate()
                ->first();

            $progress?->delete();
        });
    }
}
