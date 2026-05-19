<?php

declare(strict_types=1);

namespace App\UseCases\AdminQaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * admin モデレーション用の質問スレッド削除ユースケース。
 *
 * 公開側 DestroyAction と異なり、回答有無を不問で SoftDelete する。配下の回答は物理 cascade させず
 * 個別の SoftDelete 状態を保持する (履歴維持)。
 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            $thread->delete();
        });
    }
}
