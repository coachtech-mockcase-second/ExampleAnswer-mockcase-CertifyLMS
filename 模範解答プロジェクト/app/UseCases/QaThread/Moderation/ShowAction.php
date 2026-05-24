<?php

declare(strict_types=1);

namespace App\UseCases\QaThread\Moderation;

use App\Models\QaThread;

/**
 * admin モデレーション用の質問スレッド詳細取得ユースケース。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread->load([
            'certification',
            'user',
            'replies' => function ($q): void {
                $q->with('user')->orderBy('created_at');
            },
        ]);
    }
}
