<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserStatus;
use App\Models\QaReply;
use App\Notifications\QaBoard\QaReplyReceivedNotification;

/**
 * 質問掲示板の新規回答を質問投稿者に配信するラッパー Action。
 *
 * QaReply\StoreAction から `DB::afterCommit()` 内で呼ばれ、自己回答 (回答者 = 投稿者) と
 * 投稿者の `status !== InProgress` (withdrawn / graduated / invited) は配信スキップする。
 */
final class NotifyQaReplyReceivedAction
{
    public function __invoke(QaReply $reply): void
    {
        $reply->loadMissing(['thread.user']);

        $author = $reply->thread?->user;

        if ($author === null) {
            return;
        }
        if ($author->id === $reply->user_id) {
            return;
        }
        if ($author->status !== UserStatus::InProgress) {
            return;
        }

        $author->notify(new QaReplyReceivedNotification($reply));
    }
}
