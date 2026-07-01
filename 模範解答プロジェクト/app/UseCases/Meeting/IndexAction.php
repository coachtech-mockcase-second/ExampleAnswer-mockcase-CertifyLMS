<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Http\Controllers\MeetingController;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生本人の面談予約一覧をフィルタ込みで paginate するユースケース。
 *
 * filter は `upcoming`(予約済 + 開始時刻が未来)/ `past`(キャンセル or 完了)/ `all`(全件) の 3 値。
 * eager load は履歴 UI の表示に必要な enrollment.certification / coach を先読みする。
 *
 * @see MeetingController::index()
 */
final class IndexAction
{
    /**
     * @param 'upcoming'|'past'|'all' $filter
     *
     * @return LengthAwarePaginator<Meeting>
     */
    public function __invoke(User $student, string $filter = 'upcoming', int $perPage = 20): LengthAwarePaginator
    {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'coach'])
            ->forStudent($student)
            ->orderByDesc('scheduled_at');

        return match ($filter) {
            'past' => $query->past()->paginate($perPage),
            'all' => $query->paginate($perPage),
            default => $query->upcoming()->paginate($perPage),
        };
    }
}
