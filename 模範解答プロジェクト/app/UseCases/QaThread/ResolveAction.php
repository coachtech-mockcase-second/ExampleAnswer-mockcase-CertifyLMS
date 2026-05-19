<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadAlreadyResolvedException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドを「解決済」にマークするユースケース。
 *
 * `status = Resolved` と `resolved_at = now()` を同じ UPDATE で書き込み、2 カラム間の整合性を保つ。
 * 既に Resolved の場合は QaThreadAlreadyResolvedException (HTTP 409) を返し再マークを防ぐ。
 *
 * @throws QaThreadAlreadyResolvedException
 */
final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return DB::transaction(function () use ($thread): QaThread {
            if ($thread->status === QaThreadStatus::Resolved) {
                throw new QaThreadAlreadyResolvedException;
            }

            $thread->update([
                'status' => QaThreadStatus::Resolved,
                'resolved_at' => now(),
            ]);

            return $thread->fresh();
        });
    }
}
