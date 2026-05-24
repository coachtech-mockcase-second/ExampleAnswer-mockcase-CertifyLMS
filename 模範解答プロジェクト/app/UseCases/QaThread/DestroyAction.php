<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\UserRole;
use App\Exceptions\QaBoard\QaThreadHasRepliesException;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッド削除ユースケース。投稿者本人 / admin モデレーションの両方を 1 Action で扱う。
 *
 * - 投稿者本人による削除: 回答が 1 件でも存在すれば QaThreadHasRepliesException を throw(回答履歴の保持を優先)
 * - admin によるモデレーション削除: 回答有無を不問で削除、配下回答も先に物理削除
 *
 * @throws QaThreadHasRepliesException 投稿者本人による削除で回答 1 件以上ある場合
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread, User $auth): void
    {
        DB::transaction(function () use ($thread, $auth): void {
            if ($auth->role === UserRole::Admin) {
                $thread->replies()->delete();
                $thread->delete();

                return;
            }

            if ($thread->replies()->exists()) {
                throw new QaThreadHasRepliesException;
            }

            $thread->delete();
        });
    }
}
