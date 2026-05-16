<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\CertificationCoachAssignment;
use App\Models\Chapter;
use App\Models\Invitation;
use App\Models\Part;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\User;
use App\Policies\CertificatePolicy;
use App\Policies\CertificationCategoryPolicy;
use App\Policies\CertificationCoachAssignmentPolicy;
use App\Policies\CertificationPolicy;
use App\Policies\ChapterPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\PartPolicy;
use App\Policies\QuestionCategoryPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\SectionImagePolicy;
use App\Policies\SectionPolicy;
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
        Part::class => PartPolicy::class,
        Chapter::class => ChapterPolicy::class,
        Section::class => SectionPolicy::class,
        SectionImage::class => SectionImagePolicy::class,
        Question::class => QuestionPolicy::class,
        QuestionCategory::class => QuestionCategoryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
