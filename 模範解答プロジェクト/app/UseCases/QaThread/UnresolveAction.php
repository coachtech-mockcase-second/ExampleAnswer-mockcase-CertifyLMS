<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 解決済スレッドの解決状態を解除して open に戻すユースケース。
 *
 * `status = Open` と `resolved_at = null` を同じ UPDATE で書き込み 2 カラム間の整合性を保つ。
 * 既に Open の場合は冪等な no-op として現在のスレッドをそのまま返す
 * (二重送信 / 古いタブからの再解除でもエラーにしない)。
 */
final class UnresolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return DB::transaction(function () use ($thread): QaThread {
            if ($thread->status === QaThreadStatus::Open) {
                return $thread;
            }

            $thread->update([
                'status' => QaThreadStatus::Open,
                'resolved_at' => null,
            ]);

            return $thread->fresh();
        });
    }
}
