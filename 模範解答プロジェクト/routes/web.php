<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminChatRoomController;
use App\Http\Controllers\AiChatConversationController;
use App\Http\Controllers\AiChatMessageController;
use App\Http\Controllers\AdminEnrollmentController;
use App\Http\Controllers\AdminMockExamSessionController;
use App\Http\Controllers\AdminQaReplyController;
use App\Http\Controllers\AdminQaThreadController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificationCatalogController;
use App\Http\Controllers\CertificationCategoryController;
use App\Http\Controllers\CertificationCoachAssignmentController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\CoachStudentController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\ContentSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EnrollmentGoalController;
use App\Http\Controllers\EnrollmentNoteController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LearningHourTargetController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingQuotaCheckoutController;
use App\Http\Controllers\MeetingQuotaHistoryController;
use App\Http\Controllers\MeetingPackController;
use App\Http\Controllers\MeetingPackStatusController;
use App\Http\Controllers\MockExamAnswerController;
use App\Http\Controllers\MockExamCatalogController;
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\MockExamQuestionController;
use App\Http\Controllers\MockExamSessionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanStatusController;
use App\Http\Controllers\QaReplyController;
use App\Http\Controllers\QaThreadController;
use App\Http\Controllers\QuestionCategoryController;
use App\Http\Controllers\QuizHistoryController;
use App\Http\Controllers\QuizStatsController;
use App\Http\Controllers\ReceiveCertificateController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SectionImageController;
use App\Http\Controllers\SectionProgressController;
use App\Http\Controllers\SectionQuestionAnswerController;
use App\Http\Controllers\SectionQuestionController;
use App\Http\Controllers\SectionQuizController;
use App\Http\Controllers\SectionQuizResultController;
use App\Http\Controllers\Settings\AvailabilityController as SettingsAvailabilityController;
use App\Http\Controllers\Settings\AvatarController as SettingsAvatarController;
use App\Http\Controllers\Settings\CoachGoogleCredentialController;
use App\Http\Controllers\Settings\PasswordController as SettingsPasswordController;
use App\Http\Controllers\Settings\ProfileController as SettingsProfileController;
use App\Http\Controllers\Settings\SettingsDefaultEnrollmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeakDrillController;
use App\Http\Controllers\WeakDrillResultController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard.index')
        : redirect('/login');
});

// ============================================================
// 認証フロー(オンボーディング: 招待 URL 経由の初回登録)
// ============================================================
// signed middleware は store のみに適用し、show は Controller 内で署名検証して invalid 時に friendly view を返す
Route::get('/onboarding/{invitation}', [OnboardingController::class, 'show'])
    ->name('onboarding.show');
Route::post('/onboarding/{invitation}', [OnboardingController::class, 'store'])
    ->middleware('signed')
    ->name('onboarding.store');

// ============================================================
// 認証後の全ロール共通ルート
// ============================================================
Route::middleware('auth')->group(function () {
    // ダッシュボード
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // プロフィール設定 / パスワード変更 / アバター(全ロール共通)
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('profile', [SettingsProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [SettingsProfileController::class, 'update'])->name('profile.update');
        Route::post('avatar', [SettingsAvatarController::class, 'store'])->name('avatar.store');
        Route::delete('avatar', [SettingsAvatarController::class, 'destroy'])->name('avatar.destroy');
        Route::put('password', [SettingsPasswordController::class, 'update'])->name('password.update');
    });

    // 通知
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/popover', [NotificationController::class, 'popover'])->name('notifications.popover');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');

    // 修了証配信(graduated 受講生でも DL 可、active-learning 非適用)
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');
});

// ============================================================
// 受講生専用ルート(受講中ステータスのみ通過、卒業ステータスはロック)
// ============================================================
Route::middleware(['auth', 'role:student', 'active-learning'])->group(function () {
    // 資格カタログ(受講生視点の閲覧)
    Route::get('certifications', [CertificationCatalogController::class, 'index'])
        ->name('certifications.index');
    Route::get('certifications/{certification}', [CertificationCatalogController::class, 'show'])
        ->name('certifications.show');

    // 教材検索(登録資格内の Published Section を全文検索)
    Route::get('contents/search', [ContentSearchController::class, 'search'])
        ->name('contents.search');

    // 受講登録(受講中一覧 / 詳細 / 自己登録 / 受講解除 / 再挑戦)
    Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('enrollments/{enrollment}', [EnrollmentController::class, 'show'])->name('enrollments.show');
    Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
    Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
    Route::post('enrollments/{enrollment}/resume', [EnrollmentController::class, 'resume'])->name('enrollments.resume');

    // 修了証受領(受講生自己発火、graduated は active-learning でブロックされるため新規受領不可)
    Route::post('enrollments/{enrollment}/receive-certificate', [ReceiveCertificateController::class, 'store'])
        ->name('enrollments.receiveCertificate');

    // 個人目標(受講生本人のみ CRUD)
    Route::post('enrollments/{enrollment}/goals', [EnrollmentGoalController::class, 'store'])
        ->name('enrollments.goals.store');
    Route::patch('enrollment-goals/{goal}', [EnrollmentGoalController::class, 'update'])
        ->name('enrollment-goals.update');
    Route::delete('enrollment-goals/{goal}', [EnrollmentGoalController::class, 'destroy'])
        ->name('enrollment-goals.destroy');
    Route::post('enrollment-goals/{goal}/achieve', [EnrollmentGoalController::class, 'markAchieved'])
        ->name('enrollment-goals.markAchieved');
    Route::delete('enrollment-goals/{goal}/achieve', [EnrollmentGoalController::class, 'unmarkAchieved'])
        ->name('enrollment-goals.unmarkAchieved');
});

// ============================================================
// 受講生専用 設定ルート(デフォルト資格の永続変更)
// ============================================================
Route::middleware(['auth', 'role:student', 'active-learning'])
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::put('default-enrollment/{enrollment}', [SettingsDefaultEnrollmentController::class, 'update'])
            ->name('default-enrollment.update');
    });

// ============================================================
// 受講生専用ルート — 教材閲覧 / 読了マーク / 学習時間目標
// ============================================================
Route::middleware(['auth', 'role:student', 'active-learning'])
    ->prefix('learning')
    ->name('learning.')
    ->group(function () {
        // 教材ブラウジング
        Route::get('/', [BrowseController::class, 'index'])
            ->middleware('resolve-default-enrollment:learning.enrollments.show')
            ->name('index');
        Route::get('enrollments/{enrollment}', [BrowseController::class, 'showEnrollment'])
            ->name('enrollments.show');
        Route::get('parts/{part}', [BrowseController::class, 'showPart'])->name('parts.show');
        Route::get('chapters/{chapter}', [BrowseController::class, 'showChapter'])->name('chapters.show');
        Route::get('sections/{section}', [BrowseController::class, 'showSection'])->name('sections.show');

        // Section 読了マーク
        Route::post('sections/{section}/read', [SectionProgressController::class, 'markRead'])
            ->name('sections.markRead');
        Route::delete('sections/{section}/read', [SectionProgressController::class, 'unmarkRead'])
            ->name('sections.unmarkRead');

        // 学習時間目標
        Route::get('enrollments/{enrollment}/hour-target', [LearningHourTargetController::class, 'show'])
            ->name('hourTarget.show');
        Route::put('enrollments/{enrollment}/hour-target', [LearningHourTargetController::class, 'upsert'])
            ->name('hourTarget.upsert');
        Route::delete('enrollments/{enrollment}/hour-target', [LearningHourTargetController::class, 'destroy'])
            ->name('hourTarget.destroy');
    });

// ============================================================
// admin + コーチ共有ルート(コーチメモ: coach は担当資格内のみ、admin は越境可)
// ============================================================
Route::middleware(['auth', 'role:admin,coach'])->group(function () {
    Route::post('admin/enrollments/{enrollment}/notes', [EnrollmentNoteController::class, 'store'])
        ->name('admin.enrollments.notes.store');
    Route::patch('enrollment-notes/{note}', [EnrollmentNoteController::class, 'update'])
        ->name('enrollment-notes.update');
    Route::delete('enrollment-notes/{note}', [EnrollmentNoteController::class, 'destroy'])
        ->name('enrollment-notes.destroy');
});

// ============================================================
// admin 専用ルート
// ============================================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // ユーザー管理
    Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->withTrashed()
        ->name('admin.users.show');
    Route::post('users/{user}/withdraw', [UserController::class, 'withdraw'])->name('admin.users.withdraw');
    Route::post('users/{user}/extend-course', [UserController::class, 'extendCourse'])->name('admin.users.extendCourse');
    Route::post('users/{user}/grant-meeting-quota', [UserController::class, 'grantMeetingQuota'])->name('admin.users.grantMeetingQuota');

    // 招待管理
    Route::post('invitations', [InvitationController::class, 'store'])->name('admin.invitations.store');
    Route::post('users/{user}/resend-invitation', [InvitationController::class, 'resend'])->name('admin.invitations.resend');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('admin.invitations.destroy');

    // プラン管理(受講プラン マスタ + 状態遷移)
    Route::resource('plans', PlanController::class)
        ->parameters(['plans' => 'plan'])
        ->names('admin.plans');
    Route::post('plans/{plan}/publish', [PlanStatusController::class, 'publish'])
        ->name('admin.plans.publish');
    Route::post('plans/{plan}/archive', [PlanStatusController::class, 'archive'])
        ->name('admin.plans.archive');
    Route::post('plans/{plan}/unarchive', [PlanStatusController::class, 'unarchive'])
        ->name('admin.plans.unarchive');

    // 資格マスタ管理(資格本体の CRUD + 状態遷移、admin のみ)
    Route::resource('certifications', CertificationController::class)
        ->except(['index', 'show'])
        ->parameters(['certifications' => 'certification'])
        ->names('admin.certifications');
    Route::post('certifications/{certification}/publish', [CertificationController::class, 'publish'])
        ->name('admin.certifications.publish');
    Route::post('certifications/{certification}/unpublish', [CertificationController::class, 'unpublish'])
        ->name('admin.certifications.unpublish');
    Route::post('certifications/{certification}/archive', [CertificationController::class, 'archive'])
        ->name('admin.certifications.archive');

    // 担当コーチ割当(資格 ↔ コーチ、admin のみ)
    Route::post('certifications/{certification}/coaches/{coach}', [CertificationCoachAssignmentController::class, 'attach'])
        ->name('admin.certifications.coaches.attach');
    Route::delete('certifications/{certification}/coaches/{coach}', [CertificationCoachAssignmentController::class, 'detach'])
        ->name('admin.certifications.coaches.detach');

    // カテゴリ管理(資格分類マスタ)
    Route::resource('certification-categories', CertificationCategoryController::class)
        ->parameters(['certification-categories' => 'category'])
        ->except(['show', 'create', 'edit'])
        ->names('admin.certification-categories');

    // 面談パック管理(SKU マスタ + 状態遷移)
    Route::resource('meeting-packs', MeetingPackController::class)
        ->parameters(['meeting-packs' => 'plan'])
        ->names('admin.meeting-packs');
    Route::post('meeting-packs/{plan}/publish', [MeetingPackStatusController::class, 'publish'])
        ->name('admin.meeting-packs.publish');
    Route::post('meeting-packs/{plan}/archive', [MeetingPackStatusController::class, 'archive'])
        ->name('admin.meeting-packs.archive');
    Route::post('meeting-packs/{plan}/unarchive', [MeetingPackStatusController::class, 'unarchive'])
        ->name('admin.meeting-packs.unarchive');

    // 受講登録管理(全件一覧 / 詳細 / 試験日変更 / 手動学習中止)。新規作成は受講生自身の自己登録のみ
    Route::get('enrollments', [AdminEnrollmentController::class, 'index'])->name('admin.enrollments.index');
    Route::get('enrollments/{enrollment}', [AdminEnrollmentController::class, 'show'])
        ->withTrashed()
        ->name('admin.enrollments.show');
    Route::patch('enrollments/{enrollment}/exam-date', [AdminEnrollmentController::class, 'updateExamDate'])
        ->name('admin.enrollments.updateExamDate');
    Route::post('enrollments/{enrollment}/fail', [AdminEnrollmentController::class, 'fail'])
        ->name('admin.enrollments.fail');

    // 管理者お知らせ配信(全 in_progress 受講生 / 資格別 / ユーザー指定)
    Route::resource('announcements', AdminAnnouncementController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->parameters(['announcements' => 'announcement'])
        ->names('admin.announcements');
});

// ============================================================
// admin + コーチ共有ルート(資格マスタ閲覧 / 教材管理: コーチは担当資格のみ Policy + scope で絞り込み)
// ============================================================
Route::middleware(['auth', 'role:admin,coach'])->prefix('admin')->group(function () {
    // 資格マスタ閲覧 (admin = 全件 / coach = 担当資格のみ、Certification::scopeForUser で絞込)
    Route::resource('certifications', CertificationController::class)
        ->only(['index', 'show'])
        ->parameters(['certifications' => 'certification'])
        ->names('admin.certifications');

    // 教材管理 — Part: 一覧 / 新規作成 / 並び替え
    Route::get('certifications/{certification}/parts', [PartController::class, 'index'])
        ->name('admin.certifications.parts.index');
    Route::post('certifications/{certification}/parts', [PartController::class, 'store'])
        ->name('admin.certifications.parts.store');
    Route::patch('certifications/{certification}/parts/reorder', [PartController::class, 'reorder'])
        ->name('admin.certifications.parts.reorder');

    // 教材管理 — Part: 詳細 / 更新 / 削除 / 公開遷移 + 配下 Chapter の作成・並び替え
    Route::get('parts/{part}', [PartController::class, 'show'])->name('admin.parts.show');
    Route::patch('parts/{part}', [PartController::class, 'update'])->name('admin.parts.update');
    Route::delete('parts/{part}', [PartController::class, 'destroy'])->name('admin.parts.destroy');
    Route::post('parts/{part}/publish', [PartController::class, 'publish'])->name('admin.parts.publish');
    Route::post('parts/{part}/unpublish', [PartController::class, 'unpublish'])->name('admin.parts.unpublish');
    Route::post('parts/{part}/chapters', [ChapterController::class, 'store'])->name('admin.parts.chapters.store');
    Route::patch('parts/{part}/chapters/reorder', [ChapterController::class, 'reorder'])
        ->name('admin.parts.chapters.reorder');

    // 教材管理 — Chapter: 詳細 / 更新 / 削除 / 公開遷移 + 配下 Section の作成・並び替え
    Route::get('chapters/{chapter}', [ChapterController::class, 'show'])->name('admin.chapters.show');
    Route::patch('chapters/{chapter}', [ChapterController::class, 'update'])->name('admin.chapters.update');
    Route::delete('chapters/{chapter}', [ChapterController::class, 'destroy'])->name('admin.chapters.destroy');
    Route::post('chapters/{chapter}/publish', [ChapterController::class, 'publish'])->name('admin.chapters.publish');
    Route::post('chapters/{chapter}/unpublish', [ChapterController::class, 'unpublish'])->name('admin.chapters.unpublish');
    Route::post('chapters/{chapter}/sections', [SectionController::class, 'store'])
        ->name('admin.chapters.sections.store');
    Route::patch('chapters/{chapter}/sections/reorder', [SectionController::class, 'reorder'])
        ->name('admin.chapters.sections.reorder');

    // 教材管理 — Section: 詳細 / 更新 / 削除 / 公開遷移 / Markdown プレビュー / 画像アップロード
    Route::get('sections/{section}', [SectionController::class, 'show'])->name('admin.sections.show');
    Route::patch('sections/{section}', [SectionController::class, 'update'])->name('admin.sections.update');
    Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('admin.sections.destroy');
    Route::post('sections/{section}/publish', [SectionController::class, 'publish'])->name('admin.sections.publish');
    Route::post('sections/{section}/unpublish', [SectionController::class, 'unpublish'])->name('admin.sections.unpublish');
    Route::post('sections/{section}/preview', [SectionController::class, 'preview'])->name('admin.sections.preview');
    Route::post('sections/{section}/images', [SectionImageController::class, 'store'])
        ->name('admin.sections.images.store');

    // 教材管理 — Section 内画像の削除
    Route::delete('section-images/{image}', [SectionImageController::class, 'destroy'])
        ->name('admin.section-images.destroy');

    // 演習管理 — 出題分野マスタ
    Route::get('certifications/{certification}/question-categories', [QuestionCategoryController::class, 'index'])
        ->name('admin.certifications.question-categories.index');
    Route::post('certifications/{certification}/question-categories', [QuestionCategoryController::class, 'store'])
        ->name('admin.certifications.question-categories.store');
    Route::patch('question-categories/{category}', [QuestionCategoryController::class, 'update'])
        ->name('admin.question-categories.update');
    Route::delete('question-categories/{category}', [QuestionCategoryController::class, 'destroy'])
        ->name('admin.question-categories.destroy');

    // 模試管理 — 模試マスタ CRUD + 公開状態遷移 + 並び順
    Route::put('mock-exams/reorder', [MockExamController::class, 'reorder'])->name('admin.mock-exams.reorder');
    Route::resource('mock-exams', MockExamController::class)
        ->parameters(['mock-exams' => 'mockExam'])
        ->names('admin.mock-exams');
    Route::post('mock-exams/{mockExam}/publish', [MockExamController::class, 'publish'])
        ->name('admin.mock-exams.publish');
    Route::post('mock-exams/{mockExam}/unpublish', [MockExamController::class, 'unpublish'])
        ->name('admin.mock-exams.unpublish');

    // 模試管理 — 模試問題 CRUD(模試マスタの子リソース、shallow)
    Route::put('mock-exams/{mockExam}/questions/reorder', [MockExamQuestionController::class, 'reorder'])
        ->name('admin.mock-exams.questions.reorder');
    Route::get('mock-exams/{mockExam}/questions', [MockExamQuestionController::class, 'index'])
        ->name('admin.mock-exams.questions.index');
    Route::get('mock-exams/{mockExam}/questions/create', [MockExamQuestionController::class, 'create'])
        ->name('admin.mock-exams.questions.create');
    Route::post('mock-exams/{mockExam}/questions', [MockExamQuestionController::class, 'store'])
        ->name('admin.mock-exams.questions.store');
    Route::get('mock-exam-questions/{question}', [MockExamQuestionController::class, 'show'])
        ->name('admin.mock-exam-questions.show');
    Route::get('mock-exam-questions/{question}/edit', [MockExamQuestionController::class, 'edit'])
        ->name('admin.mock-exam-questions.edit');
    Route::put('mock-exam-questions/{question}', [MockExamQuestionController::class, 'update'])
        ->name('admin.mock-exam-questions.update');
    Route::delete('mock-exam-questions/{question}', [MockExamQuestionController::class, 'destroy'])
        ->name('admin.mock-exam-questions.destroy');

    // 模試管理 — 受講生セッション閲覧(coach は担当資格のみ)
    Route::get('mock-exam-sessions', [AdminMockExamSessionController::class, 'index'])
        ->name('admin.mock-exam-sessions.index');
    Route::get('mock-exam-sessions/{session}', [AdminMockExamSessionController::class, 'show'])
        ->name('admin.mock-exam-sessions.show');

    // 演習管理 — Section 紐づき演習問題: 一覧 / 作成 / 詳細・編集 / 公開遷移(Section 経由でのみアクセス)
    Route::get('sections/{section}/questions', [SectionQuestionController::class, 'index'])
        ->name('admin.sections.questions.index');
    Route::get('sections/{section}/questions/create', [SectionQuestionController::class, 'create'])
        ->name('admin.sections.questions.create');
    Route::post('sections/{section}/questions', [SectionQuestionController::class, 'store'])
        ->name('admin.sections.questions.store');
    Route::get('section-questions/{sectionQuestion}', [SectionQuestionController::class, 'show'])
        ->name('admin.section-questions.show');
    Route::patch('section-questions/{sectionQuestion}', [SectionQuestionController::class, 'update'])
        ->name('admin.section-questions.update');
    Route::delete('section-questions/{sectionQuestion}', [SectionQuestionController::class, 'destroy'])
        ->name('admin.section-questions.destroy');
    Route::post('section-questions/{sectionQuestion}/publish', [SectionQuestionController::class, 'publish'])
        ->name('admin.section-questions.publish');
    Route::post('section-questions/{sectionQuestion}/unpublish', [SectionQuestionController::class, 'unpublish'])
        ->name('admin.section-questions.unpublish');
});

// ============================================================
// 受講生専用ルート — 模試カタログ(/learning 配下に集約、default 資格は middleware で解決)
// ============================================================
Route::middleware(['auth', 'role:student', 'active-learning'])
    ->prefix('learning/enrollments/{enrollment}')
    ->name('mock-exam.')
    ->group(function () {
        Route::get('mock-exams', [MockExamCatalogController::class, 'index'])->name('catalog.index');
        Route::get('mock-exams/{mockExam}', [MockExamCatalogController::class, 'show'])->name('catalog.show');
        Route::post('mock-exams/{mockExam}/sessions', [MockExamSessionController::class, 'store'])
            ->name('sessions.store');
    });

// /mock-exams 直接アクセスは default 資格へ自動 redirect(default 未設定で複数 Enrollment 時のみフォールバック画面)
Route::middleware(['auth', 'role:student', 'active-learning'])->group(function () {
    Route::get('mock-exams', function () {
        // ここに到達した = default 未設定 + 残存 Enrollment が 0 件 or 2+ 件
        $user = auth()->user();
        $enrollments = $user
            ?->enrollments()
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->with('certification')
            ->get();

        return view('mock-exams.empty-state', [
            'enrollments' => $enrollments ?? collect(),
        ]);
    })
        ->middleware('resolve-default-enrollment:mock-exam.catalog.index')
        ->name('mock-exam.fallback.index');

    // 受験セッション操作群(セッション ID 直接参照、enrollment 不要)
    Route::get('mock-exam-sessions', [MockExamSessionController::class, 'index'])->name('mock-exam-sessions.index');
    Route::get('mock-exam-sessions/{session}', [MockExamSessionController::class, 'show'])
        ->name('mock-exam-sessions.show');
    Route::post('mock-exam-sessions/{session}/start', [MockExamSessionController::class, 'start'])
        ->name('mock-exam-sessions.start');
    Route::post('mock-exam-sessions/{session}/submit', [MockExamSessionController::class, 'submit'])
        ->name('mock-exam-sessions.submit');
    Route::delete('mock-exam-sessions/{session}', [MockExamSessionController::class, 'destroy'])
        ->name('mock-exam-sessions.destroy');
    Route::patch('mock-exam-sessions/{session}/answers', [MockExamAnswerController::class, 'update'])
        ->name('mock-exam-sessions.answers.update');
});

// ============================================================
// 受講生専用ルート — Section 紐づき問題演習 / 苦手分野ドリル / 解答履歴
// ============================================================
Route::middleware(['auth', 'role:student', 'active-learning'])
    ->prefix('quiz')
    ->name('quiz.')
    ->group(function () {
        // Section 経路
        Route::get('sections/{section}', [SectionQuizController::class, 'show'])
            ->name('sections.show');
        Route::get('sections/{section}/questions/{question}', [SectionQuizController::class, 'showQuestion'])
            ->name('sections.question');
        Route::get('sections/{section}/questions/{question}/result/{answer}', [SectionQuizResultController::class, 'show'])
            ->name('sections.result');

        // 苦手分野ドリル経路
        Route::get('drills/{enrollment}', [WeakDrillController::class, 'index'])
            ->name('drills.index');
        Route::get('drills/{enrollment}/categories/{questionCategory}', [WeakDrillController::class, 'showCategory'])
            ->name('drills.category');
        Route::get('drills/{enrollment}/categories/{questionCategory}/questions/{question}', [WeakDrillController::class, 'showQuestion'])
            ->name('drills.question');
        Route::get('drills/{enrollment}/categories/{questionCategory}/questions/{question}/result/{answer}', [WeakDrillResultController::class, 'show'])
            ->name('drills.result');

        // 解答送信(両経路共通エンドポイント、source 値で結果画面を分岐)
        Route::post('questions/{question}/answer', [SectionQuestionAnswerController::class, 'store'])
            ->name('answers.store');

        // 履歴・サマリ
        Route::get('history/{enrollment}', [QuizHistoryController::class, 'index'])
            ->name('history.index');
        Route::get('stats/{enrollment}', [QuizStatsController::class, 'index'])
            ->name('stats.index');
    });

// ============================================================
// 受講生専用ルート — 面談予約 (履歴一覧は資格横断 / 予約画面は default 資格に解決)
// ============================================================
Route::middleware(['auth', 'role:student', 'active-learning'])->group(function () {
    // 履歴一覧: 資格横断、Switcher 適用なし
    Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');

    // 予約画面エントリ: default 資格があれば canonical URL へ redirect、無ければ empty-state 表示
    Route::get('meetings/create', [MeetingController::class, 'createFallback'])
        ->middleware('resolve-default-enrollment:meetings.create')
        ->name('meetings.fallback.create');

    // 予約画面 canonical: URL に Enrollment を含む
    Route::prefix('enrollments/{enrollment}')->group(function () {
        Route::get('meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
        Route::get('meetings/availability', [MeetingController::class, 'fetchAvailability'])->name('meetings.availability');
        Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
    });
});

// ============================================================
// 当事者共通ルート — 面談予約の詳細 / キャンセル
// ============================================================
Route::middleware('auth')->group(function () {
    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::post('meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
});

// ============================================================
// 受講生・コーチ共有 — chat (グループルーム閲覧 / メッセージ送信)
// ============================================================
Route::middleware(['auth', 'role:student,coach', 'active-learning'])->group(function () {
    Route::get('chat-rooms', [ChatRoomController::class, 'index'])
        ->name('chat.index');
    Route::get('chat-rooms/{room}', [ChatRoomController::class, 'show'])
        ->name('chat.show');
    Route::post('chat-rooms/{room}/messages', [ChatRoomController::class, 'storeMessage'])
        ->name('chat.storeMessage');
});

// ============================================================
// コーチ専用 — chat 未読あり一覧
// ============================================================
Route::middleware(['auth', 'role:coach', 'active-learning'])->group(function () {
    Route::get('coach/chat-rooms', [ChatRoomController::class, 'indexAsCoach'])
        ->name('coach.chat.index');
});

// ============================================================
// 管理者専用 — chat 監査閲覧
// ============================================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('chat-rooms', [AdminChatRoomController::class, 'index'])
        ->name('admin.chat-rooms.index');
    Route::get('chat-rooms/{room}', [AdminChatRoomController::class, 'show'])
        ->name('admin.chat-rooms.show');
});

// ============================================================
// コーチ専用ルート — 担当資格受講生管理 / 面談管理 / メモ記録
// ============================================================
Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    // 担当資格受講生管理(担当資格に属する Enrollment の一覧 / 詳細)
    Route::get('students', [CoachStudentController::class, 'index'])->name('students.index');
    Route::get('students/{enrollment}', [CoachStudentController::class, 'show'])->name('students.show');

    // 面談管理
    Route::get('meetings', [MeetingController::class, 'indexAsCoach'])->name('meetings.index');
    Route::put('meetings/{meeting}/memo', [MeetingController::class, 'upsertMemo'])->name('meetings.memo');
});

// ============================================================
// コーチ専用ルート — Google カレンダー連携
// ============================================================
Route::middleware(['auth', 'role:coach'])->prefix('settings/google-calendar')->name('settings.google-calendar.')->group(function () {
    Route::get('connect', [CoachGoogleCredentialController::class, 'redirect'])->name('redirect');
    Route::get('callback', [CoachGoogleCredentialController::class, 'callback'])->name('callback');
    Route::delete('/', [CoachGoogleCredentialController::class, 'destroy'])->name('destroy');
});

// ============================================================
// コーチ専用ルート — 面談可能時間枠の編集
// ============================================================
Route::middleware(['auth', 'role:coach'])
    ->prefix('settings/availability')
    ->name('settings.availability.')
    ->group(function () {
        Route::get('/', [SettingsAvailabilityController::class, 'index'])->name('index');
        Route::post('/', [SettingsAvailabilityController::class, 'store'])->name('store');
        Route::patch('{availability}', [SettingsAvailabilityController::class, 'update'])->name('update');
        Route::delete('{availability}', [SettingsAvailabilityController::class, 'destroy'])->name('destroy');
    });

// ============================================================
// 受講生専用ルート(受講中=in_progress のみ通過)
// ============================================================
Route::middleware(['auth', 'role:student', 'active-learning'])->prefix('meeting-quota')->name('meeting-quota.')->group(function () {
    // 追加面談購入動線(Stripe Checkout への遷移)
    Route::get('checkout', [MeetingQuotaCheckoutController::class, 'select'])->name('checkout.select');
    Route::post('checkout', [MeetingQuotaCheckoutController::class, 'create'])->name('checkout.create');
    Route::get('success', [MeetingQuotaCheckoutController::class, 'success'])->name('success');

    // 面談回数履歴
    Route::get('history', [MeetingQuotaHistoryController::class, 'index'])->name('history');
});

// ============================================================
// 受講生 / コーチ共有 — qa-board (質問掲示板) 公開エンドポイント
// ============================================================
Route::middleware(['auth', 'role:student,coach', 'active-learning'])->group(function () {
    Route::get('qa-board', [QaThreadController::class, 'index'])->name('qa-board.index');
    Route::get('qa-board/create', [QaThreadController::class, 'create'])->name('qa-board.create');
    Route::post('qa-board', [QaThreadController::class, 'store'])->name('qa-board.store');
    Route::get('qa-board/{thread}', [QaThreadController::class, 'show'])->name('qa-board.show');
    Route::get('qa-board/{thread}/edit', [QaThreadController::class, 'edit'])->name('qa-board.edit');
    Route::patch('qa-board/{thread}', [QaThreadController::class, 'update'])->name('qa-board.update');
    Route::delete('qa-board/{thread}', [QaThreadController::class, 'destroy'])->name('qa-board.destroy');
    Route::post('qa-board/{thread}/resolve', [QaThreadController::class, 'resolve'])->name('qa-board.resolve');
    Route::post('qa-board/{thread}/unresolve', [QaThreadController::class, 'unresolve'])->name('qa-board.unresolve');

    Route::post('qa-board/{thread}/replies', [QaReplyController::class, 'store'])->name('qa-board.replies.store');
    Route::patch('qa-board/{thread}/replies/{reply}', [QaReplyController::class, 'update'])->name('qa-board.replies.update');
    Route::delete('qa-board/{thread}/replies/{reply}', [QaReplyController::class, 'destroy'])->name('qa-board.replies.destroy');
});

// ============================================================
// 管理者専用 — qa-board モデレーション
// ============================================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('qa-board', [AdminQaThreadController::class, 'index'])->name('admin.qa-board.index');
    Route::get('qa-board/{thread}', [AdminQaThreadController::class, 'show'])->withTrashed()->name('admin.qa-board.show');
    Route::delete('qa-board/{thread}', [AdminQaThreadController::class, 'destroy'])->name('admin.qa-board.destroy');
    Route::delete('qa-board/replies/{reply}', [AdminQaReplyController::class, 'destroy'])->name('admin.qa-board.replies.destroy');
});

// ============================================================
// 受講生専用 — AI 相談
// ============================================================
if ((bool) config('ai-chat.enabled', true)) {
    Route::middleware(['auth', 'role:student', 'active-learning'])
        ->prefix('ai-chat')
        ->name('ai-chat.')
        ->group(function () {
            Route::get('/', [AiChatConversationController::class, 'index'])->name('index');
            Route::post('conversations', [AiChatConversationController::class, 'store'])->name('conversations.store');
            Route::get('conversations/{conversation}', [AiChatConversationController::class, 'show'])->name('conversations.show');
            Route::patch('conversations/{conversation}', [AiChatConversationController::class, 'update'])->name('conversations.update');
            Route::delete('conversations/{conversation}', [AiChatConversationController::class, 'destroy'])->name('conversations.destroy');

            // メッセージ送信系は throttle:ai-chat で日次上限を適用
            Route::middleware('throttle:ai-chat')->group(function () {
                Route::post('conversations/{conversation}/messages', [AiChatMessageController::class, 'store'])
                    ->name('conversations.messages.store');
                Route::post('messages/{message}/retry', [AiChatMessageController::class, 'retry'])
                    ->name('messages.retry');
            });
        });
}

// ============================================================
// Webhook(認証なし、署名検証 + CSRF 除外)
// ============================================================
// Stripe Webhook: 追加面談購入の決済確定通知を受け取る(VerifyStripeSignature middleware で署名検証)
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('stripe.signature')
    ->name('webhooks.stripe');

// ============================================================
// 開発専用: 共通コンポーネントショーケース(APP_ENV=local のみ表示)
// ============================================================
if (app()->environment('local')) {
    Route::get('/_dev/components', function () {
        return view('_dev.components');
    })->name('_dev.components');
}
