<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問掲示板スレッドの詳細表示ユースケース。
 *
 * Policy 認可は Controller 側 (`$this->authorize('view', $thread)`) で実施済の前提。
 * 本 Action は与えられたスレッドに対し詳細表示に必要なリレーション (certification / user / replies.user)
 * を Eager Load して返す薄いラッパー。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread->load(['certification', 'user', 'replies.user']);
    }
}
