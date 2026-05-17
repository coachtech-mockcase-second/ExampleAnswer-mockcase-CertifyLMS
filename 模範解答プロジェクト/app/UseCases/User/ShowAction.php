<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\User;

/**
 * 管理者向けユーザー詳細画面 (`UserController::show`) のデータ集約 Action。
 *
 * 詳細画面で参照する関連(プラン / 受講登録 / 招待履歴 / ステータス履歴)を Eager Loading でまとめてロードし、
 * Blade 側 N+1 を抑制する。
 */
final class ShowAction
{
    /**
     * @return User 関連を全件 Eager Load 済の User インスタンス
     */
    public function __invoke(User $user): User
    {
        return $user->load([
            'plan',
            'enrollments.certification',
            'statusLogs' => fn ($q) => $q->orderByDesc('changed_at'),
            'statusLogs.changedBy' => fn ($q) => $q->withTrashed(),
            'invitations' => fn ($q) => $q->orderByDesc('created_at'),
            'invitations.invitedBy' => fn ($q) => $q->withTrashed(),
        ]);
    }
}
