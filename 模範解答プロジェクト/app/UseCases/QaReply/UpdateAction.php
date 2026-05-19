<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 回答編集ユースケース。投稿者本人による body の更新のみを許可する。
 *
 * `qa_thread_id` / `user_id` の差し替えは禁止 (本 Action のシグネチャ自体に含めない)。
 */
final class UpdateAction
{
    public function __invoke(QaReply $reply, string $body): QaReply
    {
        return DB::transaction(function () use ($reply, $body): QaReply {
            $reply->update(['body' => $body]);

            return $reply->fresh();
        });
    }
}
