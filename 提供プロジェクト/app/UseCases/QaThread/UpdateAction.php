<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドの編集ユースケース。投稿者本人による title / body の更新のみを許可する。
 *
 * `certification_id` / `user_id` / `status` / `resolved_at` は本 Action 経由では変更しない
 * (解決マークは ResolveAction / UnresolveAction、資格 / 投稿者 / 履歴の改ざんは禁止)。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, body: string} $validated
     */
    public function __invoke(QaThread $thread, array $validated): QaThread
    {
        return DB::transaction(function () use ($thread, $validated): QaThread {
            $thread->update([
                'title' => $validated['title'],
                'body' => $validated['body'],
            ]);

            return $thread->fresh();
        });
    }
}
