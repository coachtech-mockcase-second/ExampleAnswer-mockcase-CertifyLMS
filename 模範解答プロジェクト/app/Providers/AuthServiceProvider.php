<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\Chapter;
use App\Models\Invitation;
use App\Models\MeetingQuotaPlan;
use App\Models\Part;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\User;
use App\Policies\CertificatePolicy;
use App\Policies\CertificationCategoryPolicy;
use App\Policies\CertificationPolicy;
use App\Policies\ChapterPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\MeetingQuotaPlanPolicy;
use App\Policies\MeetingQuotaPolicy;
use App\Policies\PartPolicy;
use App\Policies\QuestionCategoryPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\SectionImagePolicy;
use App\Policies\SectionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        Certificate::class => CertificatePolicy::class,
        Part::class => PartPolicy::class,
        Chapter::class => ChapterPolicy::class,
        Section::class => SectionPolicy::class,
        SectionImage::class => SectionImagePolicy::class,
        Question::class => QuestionPolicy::class,
        QuestionCategory::class => QuestionCategoryPolicy::class,
        MeetingQuotaPlan::class => MeetingQuotaPlanPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // 追加面談購入動線(購入 / 履歴閲覧)は Model に直接紐づかない受講生 Ability として Gate 登録する
        Gate::define('purchase-meeting-quota', [MeetingQuotaPolicy::class, 'purchase']);
        Gate::define('view-meeting-quota-history', [MeetingQuotaPolicy::class, 'viewHistory']);
    }
}
