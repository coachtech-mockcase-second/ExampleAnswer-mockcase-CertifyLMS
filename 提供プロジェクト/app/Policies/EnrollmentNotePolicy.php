<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * コーチ用受講生メモ(EnrollmentNote)に対する認可ポリシー。
 *
 * - viewAny / view: 担当コーチ(certification_coach_assignments 経由) / admin
 * - create: 担当コーチ / admin
 * - update / delete: 自身が作成したノートのみ coach に許可、admin は越境可
 * - 受講生(student)は閲覧含めすべて拒否
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollmentForNotes($user, $enrollment);
    }

    public function view(User $user, EnrollmentNote $note): bool
    {
        $note->loadMissing('enrollment.certification.coaches');

        return $note->enrollment !== null
            && $this->canAccessEnrollmentForNotes($user, $note->enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollmentForNotes($user, $enrollment);
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        return $this->canModify($user, $note);
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->canModify($user, $note);
    }

    private function canAccessEnrollmentForNotes(User $user, Enrollment $enrollment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Coach) {
            return false;
        }

        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $user->id) ?? false;
    }

    private function canModify(User $user, EnrollmentNote $note): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Coach
            && $note->coach_user_id === $user->id;
    }
}
