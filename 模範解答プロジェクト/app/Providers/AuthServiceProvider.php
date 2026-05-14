<?php

namespace App\Providers;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\CertificationCoachAssignment;
use App\Models\Invitation;
use App\Models\User;
use App\Policies\CertificatePolicy;
use App\Policies\CertificationCategoryPolicy;
use App\Policies\CertificationCoachAssignmentPolicy;
use App\Policies\CertificationPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Invitation::class => InvitationPolicy::class,
        User::class => UserPolicy::class,
        Certification::class => CertificationPolicy::class,
        CertificationCategory::class => CertificationCategoryPolicy::class,
        CertificationCoachAssignment::class => CertificationCoachAssignmentPolicy::class,
        Certificate::class => CertificatePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
