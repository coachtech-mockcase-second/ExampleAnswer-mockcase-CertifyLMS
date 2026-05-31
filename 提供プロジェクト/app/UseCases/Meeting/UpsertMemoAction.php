<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use Illuminate\Support\Facades\DB;

/**
 * 担当コーチが面談メモを記録 / 編集するユースケース(reserved 段階の事前メモ / completed 段階の振り返り両方を扱う)。
 *
 * 認可(担当コーチ本人かつ reserved/completed 状態)は呼出元 Controller の `$this->authorize('upsertMemo', $meeting)` で済ませる前提で、
 * Action 側は状態の最終整合性チェック + upsert に専念する。canceled 状態の Meeting にはメモを残せない。
 *
 * @see \App\Http\Controllers\MeetingController::upsertMemo()
 */
final class UpsertMemoAction
{
    public function __invoke(Meeting $meeting, string $body): MeetingMemo
    {
        return DB::transaction(function () use ($meeting, $body) {
            if (! in_array($meeting->status, [MeetingStatus::Reserved, MeetingStatus::Completed], true)) {
                throw MeetingStatusTransitionException::forMemo();
            }

            return MeetingMemo::updateOrCreate(
                ['meeting_id' => $meeting->id],
                ['body' => $body],
            );
        });
    }
}
