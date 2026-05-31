<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 招待の開発用シーダー。
 *
 * **設計思想(Seeder 業界標準: 状態網羅 + 固定 admin 発行)**:
 *
 * 1. **status 4 種網羅**: pending / accepted / expired / revoked を全て投入。
 *    admin 招待一覧画面のフィルタ・再送/取消ボタンの活性条件を実機確認できるようにする。
 * 2. **既存 User 状態と Invitation status の整合**:
 *    - invited User → pending Invitation(オンボーディング前)
 *    - in_progress User → accepted Invitation(オンボーディング完了の履歴)
 *    - withdrawn User → expired Invitation(招待期限切れ → 自動退会のシナリオ)
 *    - withdrawn User → revoked Invitation(admin による pending 中の取消シナリオ)
 * 3. **固定 admin が発行**: invited_by_user_id は admin@certify-lms.test 固定で、admin 視点の一覧並び確認を安定化。
 *
 * 依存順序: `UserSeeder` → 本 Seeder(`EnrollmentSeeder` よりも前で問題ないが、`DatabaseSeeder` では概要把握しやすい
 *   `EnrollmentSeeder` 群の前に置く)。
 */
final class InvitationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();
        if ($admin === null) {
            $admin = User::query()->where('role', UserRole::Admin->value)->orderBy('created_at')->first();
        }

        if ($admin === null) {
            $this->command?->warn('InvitationSeeder: 管理者 User が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $this->seedPendingInvitations($admin);
        $this->seedAcceptedInvitations($admin);
        $this->seedExpiredInvitations($admin);
        $this->seedRevokedInvitations($admin);
    }

    /**
     * invited 状態の受講生に pending Invitation を 1 件ずつ作る(オンボーディング前)。
     */
    private function seedPendingInvitations(User $admin): void
    {
        $invitedUsers = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Invited->value)
            ->orderBy('created_at')
            ->get();

        foreach ($invitedUsers as $i => $user) {
            $createdAt = now()->subDays($i + 1);

            Invitation::factory()
                ->forUser($user)
                ->pending()
                ->state([
                    'invited_by_user_id' => $admin->id,
                    'expires_at' => $createdAt->copy()->addDays(7),
                    'status' => InvitationStatus::Pending->value,
                    'accepted_at' => null,
                    'revoked_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])
                ->create();
        }
    }

    /**
     * in_progress 受講生の一部に accepted Invitation を入れる(過去の招待を受領した履歴)。
     */
    private function seedAcceptedInvitations(User $admin): void
    {
        $targets = collect()
            ->push(User::query()->where('email', 'student@certify-lms.test')->first())
            ->merge(
                User::query()
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value)
                    ->whereNot('email', 'student@certify-lms.test')
                    ->orderBy('created_at')
                    ->take(3)
                    ->get()
            )
            ->filter()
            ->values();

        foreach ($targets as $i => $user) {
            $createdAt = now()->subDays(60 + $i * 5);
            $acceptedAt = $createdAt->copy()->addDays(2);

            Invitation::factory()
                ->forUser($user)
                ->accepted()
                ->state([
                    'invited_by_user_id' => $admin->id,
                    'expires_at' => $createdAt->copy()->addDays(7),
                    'accepted_at' => $acceptedAt,
                    'revoked_at' => null,
                    'status' => InvitationStatus::Accepted->value,
                    'created_at' => $createdAt,
                    'updated_at' => $acceptedAt,
                ])
                ->create();
        }
    }

    /**
     * withdrawn 受講生 1 名に expired Invitation を作る(招待期限切れ → 自動退会のシナリオ)。
     */
    private function seedExpiredInvitations(User $admin): void
    {
        $target = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Withdrawn->value)
            ->withTrashed()
            ->orderBy('created_at')
            ->first();

        if ($target === null) {
            return;
        }

        $createdAt = now()->subDays(40);
        $expiresAt = $createdAt->copy()->addDays(7);

        Invitation::factory()
            ->forUser($target)
            ->expired()
            ->state([
                'invited_by_user_id' => $admin->id,
                'expires_at' => $expiresAt,
                'accepted_at' => null,
                'revoked_at' => null,
                'status' => InvitationStatus::Expired->value,
                'created_at' => $createdAt,
                'updated_at' => $expiresAt,
            ])
            ->create();
    }

    /**
     * withdrawn 受講生 1 名に revoked Invitation を作る(admin による pending 中の取消シナリオ)。
     */
    private function seedRevokedInvitations(User $admin): void
    {
        $target = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::Withdrawn->value)
            ->withTrashed()
            ->orderBy('created_at')
            ->skip(1)
            ->first();

        if ($target === null) {
            return;
        }

        $createdAt = now()->subDays(20);
        $revokedAt = $createdAt->copy()->addDays(2);

        Invitation::factory()
            ->forUser($target)
            ->revoked()
            ->state([
                'invited_by_user_id' => $admin->id,
                'expires_at' => $createdAt->copy()->addDays(7),
                'accepted_at' => null,
                'revoked_at' => $revokedAt,
                'status' => InvitationStatus::Revoked->value,
                'created_at' => $createdAt,
                'updated_at' => $revokedAt,
            ])
            ->create();
    }
}
