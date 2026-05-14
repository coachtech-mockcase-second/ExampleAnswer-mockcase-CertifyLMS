<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy
{
    public function view(User $auth, Certificate $certificate): bool
    {
        return $auth->role === UserRole::Admin
            || $certificate->user_id === $auth->id;
    }

    public function download(User $auth, Certificate $certificate): bool
    {
        return $this->view($auth, $certificate);
    }
}
