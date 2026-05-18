<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\User;

/**
 * 面談予約 (Meeting) リソースに対する認可ポリシー。
 *
 * - viewAny: 全ロール true(取得スコープは IndexAction 側で coach_id / student_id 単位に絞る)
 * - view: 当事者(coach / student)または admin のみ
 * - create: 受講生のみ(コーチは自動割当の被選出側、admin は介入なし)
 * - cancel: 当事者かつ reserved 状態の Meeting のみ
 * - upsertMemo: 担当コーチかつ reserved / completed 状態(canceled の Meeting にはメモを残せない)
 */
class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $meeting->coach_id === $user->id,
            UserRole::Student => $meeting->student_id === $user->id,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function cancel(User $user, Meeting $meeting): bool
    {
        if ($meeting->status !== MeetingStatus::Reserved) {
            return false;
        }

        return $meeting->student_id === $user->id
            || $meeting->coach_id === $user->id;
    }

    public function upsertMemo(User $user, Meeting $meeting): bool
    {
        if ($user->role !== UserRole::Coach || $meeting->coach_id !== $user->id) {
            return false;
        }

        return in_array($meeting->status, [MeetingStatus::Reserved, MeetingStatus::Completed], true);
    }
}
