<?php

declare(strict_types=1);

namespace App\UseCases\Learning;

use App\Enums\ContentStatus;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /learning/parts/{part} (3 階層目、Chapter 一覧) のデータを準備する Action。
 *
 * 公開済 Chapter 一覧 + Part の Published 確認 (非公開なら 404) に加え、
 * 各 Chapter の Section 総数 / 読了済 Section 数 を 1 ショット SQL で集計して Blade に渡す
 * (Chapter 完了バッジの表示用)。受講生が当該資格に未登録の場合は完了数 0 として扱う。
 */
final class ShowPartAction
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Part $part, User $student): array
    {
        $part->loadMissing('certification');

        if ($part->status !== ContentStatus::Published) {
            throw new NotFoundHttpException;
        }

        $chapters = $part->chapters()
            ->where('status', ContentStatus::Published->value)
            ->ordered()
            ->withCount([
                'sections as sections_total_count' => fn ($q) => $q
                    ->where('status', ContentStatus::Published->value),
            ])
            ->get();

        $enrollment = $student->enrollments()
            ->where('certification_id', $part->certification_id)
            ->first();

        $completedByChapter = [];
        if ($enrollment !== null && $chapters->isNotEmpty()) {
            $rows = DB::table('sections')
                ->join('section_progresses', function ($join) use ($enrollment) {
                    $join->on('section_progresses.section_id', '=', 'sections.id')
                        ->where('section_progresses.enrollment_id', '=', $enrollment->id);
                })
                ->whereIn('sections.chapter_id', $chapters->pluck('id'))
                ->where('sections.status', ContentStatus::Published->value)
                ->groupBy('sections.chapter_id')
                ->selectRaw('sections.chapter_id AS chapter_id, COUNT(*) AS done')
                ->get();

            foreach ($rows as $row) {
                $completedByChapter[(string) $row->chapter_id] = (int) $row->done;
            }
        }

        return [
            'part' => $part->load('certification'),
            'chapters' => $chapters,
            'completedByChapter' => $completedByChapter,
        ];
    }
}
