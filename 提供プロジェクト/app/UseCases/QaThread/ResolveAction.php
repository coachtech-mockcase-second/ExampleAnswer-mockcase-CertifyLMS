<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドを「解決済」にマークするユースケース。
 *
 * `status = Resolved` と `resolved_at = now()` を同じ UPDATE で書き込み、2 カラム間の整合性を保つ。
 * 既に Resolved の場合は冪等な no-op として `resolved_at` を保持したまま現在のスレッドを返す
 * (二重送信 / 古いタブからの再マークでもエラーにせず、最初の解決日時を維持する)。
 */
final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return DB::transaction(function () use ($thread): QaThread {
            if ($thread->status === QaThreadStatus::Resolved) {
                return $thread;
            }

            $thread->update([
                'status' => QaThreadStatus::Resolved,
                'resolved_at' => now(),
            ]);

            return $thread->fresh();
        });
    }
}
