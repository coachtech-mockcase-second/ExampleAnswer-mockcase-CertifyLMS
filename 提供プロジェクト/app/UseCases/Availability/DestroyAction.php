<?php

declare(strict_types=1);

namespace App\UseCases\Availability;

use App\Models\CoachAvailability;
use Illuminate\Support\Facades\DB;

/**
 * コーチ本人の面談可能時間枠を SoftDelete するユースケース。
 *
 * 物理削除はしない(運用ガイダンス: 「一時休業」用途や受講生側の予約履歴整合のため、行は残す)。
 * 本人所有確認は Controller / FormRequest で完了済の前提。
 */
final class DestroyAction
{
    public function __invoke(CoachAvailability $availability): void
    {
        DB::transaction(fn () => $availability->delete());
    }
}
