<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificationCatalogController;
use App\Http\Controllers\CertificationCategoryController;
use App\Http\Controllers\CertificationCoachAssignmentController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\ContentSearchController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MeetingQuotaCheckoutController;
use App\Http\Controllers\MeetingQuotaHistoryController;
use App\Http\Controllers\MeetingQuotaPlanController;
use App\Http\Controllers\MeetingQuotaPlanStatusController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanStatusController;
use App\Http\Controllers\QuestionCategoryController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SectionImageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Middleware\EnsureActiveLearning;
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
    Route::view('/dashboard', 'placeholders.coming-soon', ['feature' => 'dashboard'])
        ->name('dashboard.index');

    // プロフィール設定
    Route::view('/settings/profile', 'placeholders.coming-soon', ['feature' => 'settings-profile'])
        ->name('settings.profile.edit');

    // 通知
    Route::view('/notifications', 'placeholders.coming-soon', ['feature' => 'notification'])
        ->name('notifications.index');

    // 資格カタログ(受講生視点の閲覧)
    Route::get('certifications', [CertificationCatalogController::class, 'index'])
        ->name('certifications.index');
    Route::get('certifications/{certification}', [CertificationCatalogController::class, 'show'])
        ->name('certifications.show');

    // 修了証配信
    Route::get('certificates/{certificate}', [CertificateController::class, 'show'])
        ->name('certificates.show');
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');

    // 教材検索
    Route::get('contents/search', [ContentSearchController::class, 'search'])
        ->name('contents.search');
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
    Route::patch('users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.updateRole');
    Route::post('users/{user}/withdraw', [UserController::class, 'withdraw'])->name('admin.users.withdraw');

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

    // 資格マスタ管理(資格本体 + 状態遷移)
    Route::resource('certifications', CertificationController::class)
        ->parameters(['certifications' => 'certification'])
        ->names('admin.certifications');
    Route::post('certifications/{certification}/publish', [CertificationController::class, 'publish'])
        ->name('admin.certifications.publish');
    Route::post('certifications/{certification}/archive', [CertificationController::class, 'archive'])
        ->name('admin.certifications.archive');
    Route::post('certifications/{certification}/unarchive', [CertificationController::class, 'unarchive'])
        ->name('admin.certifications.unarchive');

    // 担当コーチ割当(資格 ↔ コーチ)
    Route::post('certifications/{certification}/coaches', [CertificationCoachAssignmentController::class, 'store'])
        ->name('admin.certifications.coaches.store');
    Route::delete('certifications/{certification}/coaches/{user}', [CertificationCoachAssignmentController::class, 'destroy'])
        ->name('admin.certifications.coaches.destroy');

    // カテゴリ管理(資格分類マスタ)
    Route::resource('certification-categories', CertificationCategoryController::class)
        ->parameters(['certification-categories' => 'category'])
        ->except(['show', 'create', 'edit'])
        ->names('admin.certification-categories');

    // 追加面談プラン管理(SKU マスタ + 状態遷移)
    Route::resource('meeting-quota-plans', MeetingQuotaPlanController::class)
        ->parameters(['meeting-quota-plans' => 'plan'])
        ->names('admin.meeting-quota-plans');
    Route::post('meeting-quota-plans/{plan}/publish', [MeetingQuotaPlanStatusController::class, 'publish'])
        ->name('admin.meeting-quota-plans.publish');
    Route::post('meeting-quota-plans/{plan}/archive', [MeetingQuotaPlanStatusController::class, 'archive'])
        ->name('admin.meeting-quota-plans.archive');
    Route::post('meeting-quota-plans/{plan}/unarchive', [MeetingQuotaPlanStatusController::class, 'unarchive'])
        ->name('admin.meeting-quota-plans.unarchive');
});

// ============================================================
// admin + コーチ共有ルート(教材管理: コーチは担当資格のみ Policy で絞り込み)
// ============================================================
Route::middleware(['auth', 'role:admin,coach'])->prefix('admin')->group(function () {
    // 教材管理 — Part(章): 一覧 / 新規作成 / 並び替え
    Route::get('certifications/{certification}/parts', [PartController::class, 'index'])
        ->name('admin.certifications.parts.index');
    Route::post('certifications/{certification}/parts', [PartController::class, 'store'])
        ->name('admin.certifications.parts.store');
    Route::patch('certifications/{certification}/parts/reorder', [PartController::class, 'reorder'])
        ->name('admin.certifications.parts.reorder');

    // 教材管理 — Part: 詳細 / 更新 / 削除 / 公開遷移 + 配下 Chapter 操作
    Route::get('parts/{part}', [PartController::class, 'show'])->name('admin.parts.show');
    Route::patch('parts/{part}', [PartController::class, 'update'])->name('admin.parts.update');
    Route::delete('parts/{part}', [PartController::class, 'destroy'])->name('admin.parts.destroy');
    Route::post('parts/{part}/publish', [PartController::class, 'publish'])->name('admin.parts.publish');
    Route::post('parts/{part}/unpublish', [PartController::class, 'unpublish'])->name('admin.parts.unpublish');
    Route::post('parts/{part}/chapters', [ChapterController::class, 'store'])->name('admin.parts.chapters.store');
    Route::patch('parts/{part}/chapters/reorder', [ChapterController::class, 'reorder'])
        ->name('admin.parts.chapters.reorder');

    // 教材管理 — Chapter(節): 詳細 / 更新 / 削除 / 公開遷移 + 配下 Section 操作
    Route::get('chapters/{chapter}', [ChapterController::class, 'show'])->name('admin.chapters.show');
    Route::patch('chapters/{chapter}', [ChapterController::class, 'update'])->name('admin.chapters.update');
    Route::delete('chapters/{chapter}', [ChapterController::class, 'destroy'])->name('admin.chapters.destroy');
    Route::post('chapters/{chapter}/publish', [ChapterController::class, 'publish'])->name('admin.chapters.publish');
    Route::post('chapters/{chapter}/unpublish', [ChapterController::class, 'unpublish'])->name('admin.chapters.unpublish');
    Route::post('chapters/{chapter}/sections', [SectionController::class, 'store'])
        ->name('admin.chapters.sections.store');
    Route::patch('chapters/{chapter}/sections/reorder', [SectionController::class, 'reorder'])
        ->name('admin.chapters.sections.reorder');

    // 教材管理 — Section(本文): 詳細 / 更新 / 削除 / 公開遷移 / Markdown プレビュー / 画像アップロード
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

    // 演習管理 — QuestionCategory(問題分類マスタ)
    Route::get('certifications/{certification}/question-categories', [QuestionCategoryController::class, 'index'])
        ->name('admin.certifications.question-categories.index');
    Route::post('certifications/{certification}/question-categories', [QuestionCategoryController::class, 'store'])
        ->name('admin.certifications.question-categories.store');
    Route::patch('question-categories/{category}', [QuestionCategoryController::class, 'update'])
        ->name('admin.question-categories.update');
    Route::delete('question-categories/{category}', [QuestionCategoryController::class, 'destroy'])
        ->name('admin.question-categories.destroy');

    // 演習管理 — Question(問題): 一覧 / 作成 / 詳細・編集 / 公開遷移
    Route::get('certifications/{certification}/questions', [QuestionController::class, 'index'])
        ->name('admin.certifications.questions.index');
    Route::get('certifications/{certification}/questions/create', [QuestionController::class, 'create'])
        ->name('admin.certifications.questions.create');
    Route::post('certifications/{certification}/questions', [QuestionController::class, 'store'])
        ->name('admin.certifications.questions.store');
    Route::get('questions/{question}', [QuestionController::class, 'show'])->name('admin.questions.show');
    Route::patch('questions/{question}', [QuestionController::class, 'update'])->name('admin.questions.update');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('admin.questions.destroy');
    Route::post('questions/{question}/publish', [QuestionController::class, 'publish'])
        ->name('admin.questions.publish');
    Route::post('questions/{question}/unpublish', [QuestionController::class, 'unpublish'])
        ->name('admin.questions.unpublish');
});

// ============================================================
// 受講生専用ルート(受講中=in_progress のみ通過)
// ============================================================
Route::middleware(['auth', 'role:student', EnsureActiveLearning::class])->prefix('meeting-quota')->name('meeting-quota.')->group(function () {
    // 追加面談購入動線(Stripe Checkout への遷移)
    Route::get('checkout', [MeetingQuotaCheckoutController::class, 'select'])->name('checkout.select');
    Route::post('checkout', [MeetingQuotaCheckoutController::class, 'create'])->name('checkout.create');
    Route::get('success', [MeetingQuotaCheckoutController::class, 'success'])->name('success');

    // 面談回数履歴
    Route::get('history', [MeetingQuotaHistoryController::class, 'index'])->name('history');
});

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
