<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドへの回答投稿ユースケース。回答を INSERT して返す。
 */
final class StoreAction
{
    public function __invoke(QaThread $thread, User $replier, string $body): QaReply
    {
        return DB::transaction(function () use ($thread, $replier, $body): QaReply {
            return QaReply::create([
                'qa_thread_id' => $thread->id,
                'user_id' => $replier->id,
                'body' => $body,
            ]);
        });
    }
}
