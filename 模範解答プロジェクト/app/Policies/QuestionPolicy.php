<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $certification),
            default => false,
        };
    }

    public function view(User $auth, Question $question): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role === UserRole::Coach) {
            return $this->assignedCoach($auth, $question->certification);
        }

        if ($question->status !== ContentStatus::Published) {
            return false;
        }

        return $auth->enrollments()
            ->where('certification_id', $question->certification_id)
            ->exists();
    }

    public function create(User $auth, Certification $certification): bool
    {
        return $this->canManage($auth, $certification);
    }

    public function update(User $auth, Question $question): bool
    {
        return $this->canManage($auth, $question->certification);
    }

    public function delete(User $auth, Question $question): bool
    {
        return $this->canManage($auth, $question->certification);
    }

    public function publish(User $auth, Question $question): bool
    {
        return $this->canManage($auth, $question->certification);
    }

    public function unpublish(User $auth, Question $question): bool
    {
        return $this->canManage($auth, $question->certification);
    }

    private function canManage(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $certification),
            default => false,
        };
    }

    private function assignedCoach(User $coach, Certification $certification): bool
    {
        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
