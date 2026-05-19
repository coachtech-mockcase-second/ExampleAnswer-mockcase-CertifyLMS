<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * QaReply リソースに対する認可ポリシー。
 *
 * - create: admin は不可 (閲覧 + 削除のみ) / coach は担当資格のスレッドのみ / student は公開資格のみ
 * - update: 投稿者本人のみ (admin 代行不可)
 * - delete: 投稿者本人 または admin (モデレーション)
 */
class QaReplyPolicy
{
    public function create(User $user, QaThread $thread): bool
    {
        return match ($user->role) {
            UserRole::Admin => false,
            UserRole::Coach => in_array($thread->certification_id, $user->coachingCertificationIds(), true),
            UserRole::Student => $thread->certification?->status === CertificationStatus::Published,
        };
    }

    public function update(User $user, QaReply $reply): bool
    {
        return $reply->user_id === $user->id;
    }

    public function delete(User $user, QaReply $reply): bool
    {
        return $reply->user_id === $user->id
            || $user->role === UserRole::Admin;
    }
}
