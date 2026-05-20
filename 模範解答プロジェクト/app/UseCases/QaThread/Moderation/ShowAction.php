<?php

declare(strict_types=1);

namespace App\UseCases\QaThread\Moderation;

use App\Models\QaThread;

/**
 * admin モデレーション用の質問スレッド詳細取得ユースケース。
 *
 * `withTrashedReplies = true` で SoftDelete 済の回答も含めて Eager Load する
 * (admin はモデレーション履歴として削除済回答の本文も閲覧する)。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread, bool $withTrashedReplies = false): QaThread
    {
        return $thread->load([
            'certification',
            'user',
            'replies' => function ($q) use ($withTrashedReplies): void {
                if ($withTrashedReplies) {
                    $q->withTrashed();
                }
                $q->with('user')->orderBy('created_at');
            },
        ]);
    }
}
