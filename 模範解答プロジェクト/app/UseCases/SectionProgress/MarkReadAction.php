<?php

declare(strict_types=1);

namespace App\UseCases\SectionProgress;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\Learning\EnrollmentInactiveException;
use App\Exceptions\Learning\SectionUnavailableForProgressException;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Section の読了マーク UPSERT を行う Action。
 *
 * - cascade visibility 検証 (Section / 親 Chapter / 親 Part が全て Published かつ SoftDelete されていない)
 * - Enrollment 状態検証 (`learning` / `passed` 許容、`failed` で 409)
 * - SoftDelete 済 SectionProgress があれば restore + completed_at UPDATE、無ければ INSERT (冪等)
 *
 * @throws SectionUnavailableForProgressException Section / 親が Draft / SoftDelete
 * @throws EnrollmentInactiveException            Enrollment が failed 状態
 */
final class MarkReadAction
{
    public function __invoke(User $student, Section $section): SectionProgress
    {
        $section->loadMissing('chapter.part.certification');
        $chapter = $section->chapter;
        $part = $chapter?->part;
        $certification = $part?->certification;

        if ($certification === null) {
            throw new SectionUnavailableForProgressException;
        }

        if ($section->status !== ContentStatus::Published || $section->deleted_at !== null
            || $chapter->status !== ContentStatus::Published || $chapter->deleted_at !== null
            || $part->status !== ContentStatus::Published || $part->deleted_at !== null) {
            throw new SectionUnavailableForProgressException;
        }

        $enrollment = $student->enrollments()
            ->where('certification_id', $certification->id)
            ->first();

        if ($enrollment === null) {
            throw new AccessDeniedHttpException('対象の資格に受講登録していません。');
        }

        if ($enrollment->status === EnrollmentStatus::Failed) {
            throw new EnrollmentInactiveException;
        }

        return DB::transaction(function () use ($enrollment, $section) {
            $progress = SectionProgress::query()
                ->withTrashed()
                ->where('enrollment_id', $enrollment->id)
                ->where('section_id', $section->id)
                ->lockForUpdate()
                ->first();

            if ($progress === null) {
                return SectionProgress::create([
                    'enrollment_id' => $enrollment->id,
                    'section_id' => $section->id,
                    'completed_at' => now(),
                ]);
            }

            if ($progress->trashed()) {
                $progress->restore();
            }

            $progress->update(['completed_at' => now()]);

            return $progress->refresh();
        });
    }
}
