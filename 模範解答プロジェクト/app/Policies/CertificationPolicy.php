<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\User;

/**
 * 資格マスタの認可ルール。admin は全件 CRUD / coach は担当資格のみ閲覧可 / student は公開済のみ閲覧可。
 * 担当コーチ割当（attachCoach / detachCoach）も本 Policy 内に集約する。
 *
 * 一覧画面の表示行絞込みは Policy ではなく Eloquent local scope `Certification::scopeForUser(User)` で実装する
 * (admin = 全件 / coach = 担当資格のみ)。viewAny は admin / coach の両ロールに対して一覧画面到達を許可する。
 */
class CertificationPolicy
{
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [UserRole::Admin, UserRole::Coach], true);
    }

    public function view(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $certification->coaches->contains('id', $auth->id),
            UserRole::Student => $certification->status === CertificationStatus::Published
                && $certification->deleted_at === null,
        };
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function publish(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unpublish(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function attachCoach(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function detachCoach(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
