<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 受講生の予約画面が呼ぶ空き枠取得ユースケース。
 *
 * 指定 Enrollment の担当コーチ集合について、指定日 1 日分の 60 分単位空きスロット集合を返す。
 * コーチ個別は受講生に見せず、`available_coach_count` (予約可能なコーチ数) のみヒント表示する。
 *
 * @see \App\Http\Controllers\MeetingController::fetchAvailability()
 */
final class FetchAvailabilityAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
    ) {}

    /**
     * @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
     */
    public function __invoke(Enrollment $enrollment, Carbon $date): Collection
    {
        return $this->availabilityService->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $date,
        );
    }
}
