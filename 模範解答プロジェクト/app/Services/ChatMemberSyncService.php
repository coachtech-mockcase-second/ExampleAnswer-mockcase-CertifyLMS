<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use Illuminate\Support\Facades\DB;

/**
 * ChatRoom と参加者(受講生 + 担当コーチ集合)の整合を取る Service。
 *
 * 呼出元は 2 経路のみ:
 *
 * - 受講登録時: `App\UseCases\Enrollment\StoreAction` 内で `syncForRoom($room)` を呼び、
 *   受講生本人 + 当該資格のコーチ集合を eager 生成する
 * - 担当コーチ集合変更時: `App\Listeners\SyncChatMembersOnCoachAssignmentChanged` 内で
 *   `syncForCertification($certification)` を呼び、当該資格の全 ChatRoom に対し差分追加する
 *
 * 本 Service 自体は `DB::transaction()` を持たない。呼出元 Action 側のトランザクションに
 * 包まれる前提でステートレスな upsert / 削除を行う。
 *
 * `final` 不採用: Mockery で mock するテストを想定するため(`backend-types-and-docblocks.md`)。
 */
class ChatMemberSyncService
{
    /**
     * 単一 ChatRoom について、参加者を「受講生 + 担当資格のコーチ集合」と一致させる。
     *
     * - 既に存在するメンバーは触らない(`last_read_at` を巻き戻さない)
     * - 欠損しているメンバーのみ INSERT(`joined_at = now()`)
     * - SoftDelete 済の同 user 行があれば復活させて再参加扱いとする
     */
    public function syncForRoom(ChatRoom $room): void
    {
        $room->loadMissing('enrollment.certification.coaches');

        $expectedUserIds = collect()
            ->push($room->enrollment->user_id)
            ->merge($room->enrollment->certification->coaches->pluck('id'))
            ->unique()
            ->values();

        $existing = ChatMember::withTrashed()
            ->where('chat_room_id', $room->id)
            ->whereIn('user_id', $expectedUserIds)
            ->get()
            ->keyBy('user_id');

        $now = now();

        foreach ($expectedUserIds as $userId) {
            $member = $existing->get($userId);

            if ($member === null) {
                ChatMember::create([
                    'chat_room_id' => $room->id,
                    'user_id' => $userId,
                    'last_read_at' => null,
                    'joined_at' => $now,
                ]);

                continue;
            }

            if ($member->trashed()) {
                $member->restore();
                $member->update(['joined_at' => $now]);
            }
        }
    }

    /**
     * 指定資格に紐づく全 ChatRoom の ChatMember を一括同期する。
     *
     * 担当コーチ集合の追加 / 解除が起きた際に Listener から呼ばれる。各 ChatRoom について
     * `syncForRoom` を反復し、欠損 ChatMember を埋める(既存 last_read_at は保持)。
     */
    public function syncForCertification(Certification $certification): void
    {
        DB::transaction(function () use ($certification): void {
            ChatRoom::query()
                ->whereHas('enrollment', function ($q) use ($certification): void {
                    $q->where('certification_id', $certification->id);
                })
                ->with('enrollment.certification.coaches')
                ->chunkById(100, function ($rooms): void {
                    foreach ($rooms as $room) {
                        $this->syncForRoom($room);
                    }
                });
        });
    }
}
