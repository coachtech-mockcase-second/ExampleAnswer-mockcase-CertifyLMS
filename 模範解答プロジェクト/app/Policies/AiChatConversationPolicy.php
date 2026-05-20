<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AI 相談会話リソースの認可ポリシー。
 *
 * - student role + in_progress status のみ機能を利用可能 (graduated / withdrawn は EnsureActiveLearning 側で先に弾かれるが、Policy でも防衛)
 * - 他受講生の会話を一切閲覧 / 編集 / 削除できない (admin / coach も例外なく不可)
 * - admin / coach バイパスを before() で定義しない (本 Feature は student プライベートな履歴を扱う)
 */
class AiChatConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Student && $user->status === UserStatus::InProgress;
    }

    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $this->viewAny($user) && $conversation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
