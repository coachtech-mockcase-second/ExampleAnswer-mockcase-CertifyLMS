<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;

/**
 * 面談予約の詳細を当事者向けに eager load して返すユースケース。
 *
 * 詳細画面で表示する関連(enrollment / certification / coach / student / meetingMemo)を 1 リクエストで揃える。
 * 認可は Controller の `$this->authorize('view', $meeting)` で済ませる前提で、本 Action は読み取りのみ。
 *
 * @see \App\Http\Controllers\MeetingController::show()
 */
final class ShowAction
{
    public function __invoke(Meeting $meeting): Meeting
    {
        return $meeting->loadMissing([
            'enrollment.certification',
            'coach',
            'student',
            'canceledBy',
            'meetingMemo',
        ]);
    }
}
