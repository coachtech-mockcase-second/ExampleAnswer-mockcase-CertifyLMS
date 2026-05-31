<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\Notification\NotifyQaReplyReceivedAction;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドへの回答投稿ユースケース。
 *
 * INSERT 完了後、`DB::afterCommit()` で投稿者への通知ラッパー Action を起動する。
 * 自己回答 (投稿者本人による自分のスレッドへの回答) のスキップは NotifyQaReplyReceivedAction 側で実施する。
 */
final class StoreAction
{
    public function __construct(
        private readonly NotifyQaReplyReceivedAction $notify,
    ) {}

    public function __invoke(QaThread $thread, User $replier, string $body): QaReply
    {
        return DB::transaction(function () use ($thread, $replier, $body): QaReply {
            $reply = QaReply::create([
                'qa_thread_id' => $thread->id,
                'user_id' => $replier->id,
                'body' => $body,
            ]);

            DB::afterCommit(function () use ($reply): void {
                ($this->notify)($reply);
            });

            return $reply;
        });
    }
}
