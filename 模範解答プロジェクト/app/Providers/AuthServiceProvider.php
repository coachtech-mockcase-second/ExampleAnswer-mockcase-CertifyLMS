<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\EnrollmentNote;
use App\Models\Invitation;
use App\Models\LearningHourTarget;
use App\Models\LearningSession;
use App\Models\MeetingQuotaPlan;
use App\Models\Part;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\SectionProgress;
use App\Models\SectionQuestion;
use App\Models\User;
use App\Policies\CertificatePolicy;
use App\Policies\CertificationCategoryPolicy;
use App\Policies\CertificationPolicy;
use App\Policies\ChapterPolicy;
use App\Policies\ChapterViewPolicy;
use App\Policies\EnrollmentGoalPolicy;
use App\Policies\EnrollmentNotePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\LearningHourTargetPolicy;
use App\Policies\LearningSessionPolicy;
use App\Policies\MeetingQuotaPlanPolicy;
use App\Policies\MeetingQuotaPolicy;
use App\Policies\PartPolicy;
use App\Policies\PartViewPolicy;
use App\Policies\QuestionCategoryPolicy;
use App\Policies\SectionImagePolicy;
use App\Policies\SectionPolicy;
use App\Policies\SectionProgressPolicy;
use App\Policies\SectionQuestionPolicy;
use App\Policies\SectionViewPolicy;
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
        SectionQuestion::class => SectionQuestionPolicy::class,
        QuestionCategory::class => QuestionCategoryPolicy::class,
        MeetingQuotaPlan::class => MeetingQuotaPlanPolicy::class,
        Enrollment::class => EnrollmentPolicy::class,
        EnrollmentGoal::class => EnrollmentGoalPolicy::class,
        EnrollmentNote::class => EnrollmentNotePolicy::class,
        SectionProgress::class => SectionProgressPolicy::class,
        LearningSession::class => LearningSessionPolicy::class,
        LearningHourTarget::class => LearningHourTargetPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // 追加面談購入動線(購入 / 履歴閲覧)は Model に直接紐づかない受講生 Ability として Gate 登録する
        Gate::define('purchase-meeting-quota', [MeetingQuotaPolicy::class, 'purchase']);
        Gate::define('view-meeting-quota-history', [MeetingQuotaPolicy::class, 'viewHistory']);

        // 受講生視点の教材閲覧認可: 既存の admin / coach 用 PartPolicy / ChapterPolicy / SectionPolicy が
        // Model::class に auto-bind されているため、別 Gate 名で受講生用 View Policy を登録して両立させる。
        Gate::define('learning.part.view', [PartViewPolicy::class, 'view']);
        Gate::define('learning.chapter.view', [ChapterViewPolicy::class, 'view']);
        Gate::define('learning.section.view', [SectionViewPolicy::class, 'view']);
    }
}
