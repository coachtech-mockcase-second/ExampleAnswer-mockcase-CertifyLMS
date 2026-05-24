<?php

declare(strict_types=1);

namespace App\UseCases\Learning;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\SectionProgress;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /learning/chapters/{chapter} (4 階層目、Section 一覧) のデータを準備する Action。
 *
 * cascade visibility (Chapter / 親 Part のいずれかが Draft / SoftDelete) で 404、
 * 公開済 Section 一覧と受講生の読了済 Section ID 配列を併せて返す (Section 行の読了バッジ用)。
 */
final class ShowChapterAction
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Chapter $chapter, User $student): array
    {
        $chapter->loadMissing('part');

        if ($chapter->status !== ContentStatus::Published
            || $chapter->part === null
            || $chapter->part->status !== ContentStatus::Published) {
            throw new NotFoundHttpException;
        }

        $sections = $chapter->sections()
            ->where('status', ContentStatus::Published->value)
            ->ordered()
            ->get();

        $enrollment = $student->enrollments()
            ->where('certification_id', $chapter->part->certification_id)
            ->first();

        $completedSectionIds = [];
        if ($enrollment !== null && $sections->isNotEmpty()) {
            $completedSectionIds = SectionProgress::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('section_id', $sections->pluck('id'))
                ->pluck('section_id')
                ->all();
        }

        return [
            'chapter' => $chapter,
            'sections' => $sections,
            'completedSectionIds' => $completedSectionIds,
        ];
    }
}
