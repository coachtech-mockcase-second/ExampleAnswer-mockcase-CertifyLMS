<?php

declare(strict_types=1);

namespace App\UseCases\QaThread\Moderation;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * admin モデレーション用の質問スレッド削除ユースケース。
 *
 * 公開側 DestroyAction と異なり、回答有無を不問でスレッドを削除する(配下の回答も先に物理削除)。
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            $thread->replies()->delete();
            $thread->delete();
        });
    }
}
