<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\User;

/**
 * 教材内画像(SectionImage) の認可ポリシー。コーチは担当資格配下のセクション、admin は全資格でアップロード / 削除可。
 */
class SectionImagePolicy
{
    public function create(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function delete(User $auth, SectionImage $image): bool
    {
        return $this->canManage($auth, $image->section->chapter->part->certification);
    }

    private function canManage(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => false,
            default => false,
        };
    }
}
