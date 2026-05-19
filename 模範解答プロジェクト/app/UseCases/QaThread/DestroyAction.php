<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Exceptions\QaBoard\QaThreadHasRepliesException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 投稿者本人による質問スレッド削除ユースケース。
 *
 * 削除可否のドメインガード: SoftDelete 済も含めて回答が 1 件でも存在すれば QaThreadHasRepliesException
 * を throw (回答履歴の保持を優先)。admin によるモデレーション削除は AdminQaThread\DestroyAction を使う。
 *
 * @throws QaThreadHasRepliesException
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            if ($thread->replies()->withTrashed()->exists()) {
                throw new QaThreadHasRepliesException;
            }

            $thread->delete();
        });
    }
}
