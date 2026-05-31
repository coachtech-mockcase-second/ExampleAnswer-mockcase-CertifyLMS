<?php

declare(strict_types=1);

namespace App\UseCases\MockExamSession;

use App\Models\MockExamSession;

/**
 * 模試受験セッションの詳細を status 別 Blade 描画用に整形して取得するユースケース。
 *
 * NotStarted: lobby 用(模試概要 + 問題数)
 * InProgress: take 用(出題スナップショット + 既存解答)
 * Submitted / Graded: result 用(採点結果 + 解答)
 * Canceled: canceled 用(戻り導線のみ)
 */
final class ShowAction
{
    public function __invoke(MockExamSession $session): MockExamSession
    {
        return $session->load([
            'mockExam.certification',
            'enrollment',
            'user',
            'answers',
        ]);
    }
}
