<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;

/**
 * Schedule Command から呼ばれる自動完了ユースケース。
 *
 * `scheduled_at + 60 分` を過ぎた `reserved` Meeting を `completed` に遷移する。通知は発火しない
 * (受講生・コーチとも履歴一覧で確認できる + 通知過剰を避ける)。
 *
 * 複数台 worker が並行起動して二重遷移が走るのを防ぐため、`lockForUpdate()` で対象行を取得し直し
 * トランザクション内でステータスを再確認してから UPDATE する(冪等)。
 *
 * @see \App\Console\Commands\Mentoring\AutoCompleteMeetingsCommand
 */
final class AutoCompleteMeetingAction
{
    public function __invoke(Meeting $meeting): Meeting
    {
        return DB::transaction(function () use ($meeting) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                return $meeting;
            }

            $locked->update([
                'status' => MeetingStatus::Completed->value,
                'completed_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}
