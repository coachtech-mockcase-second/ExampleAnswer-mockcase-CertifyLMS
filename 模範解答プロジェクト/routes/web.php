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
use App\Http\Controllers\PartController;
use App\Http\Controllers\QuestionCategoryController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SectionImageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Wave 0b 骨組み: 各 Feature が実装フェーズで自身のルートを追加していく。
| ここではナビゲーション (topbar / sidebar) から `Route::has()` で参照される
| プレースホルダのみを定義する。
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard.index')
        : redirect('/login');
});

// [[auth]] 招待 URL からのオンボーディング（署名付き URL が認可、auth middleware は付けない）
// - show: signed middleware を付けず、Controller 内で verify して invalid 時は friendly view（REQ-auth-020）
// - store: signed middleware で署名を強制検証、改竄時は 403（REQ-auth-022）
Route::get('/onboarding/{invitation}', [OnboardingController::class, 'show'])
    ->name('onboarding.show');
Route::post('/onboarding/{invitation}', [OnboardingController::class, 'store'])
    ->middleware('signed')
    ->name('onboarding.store');

Route::middleware('auth')->group(function () {
    // [[dashboard]] が実装される
    Route::view('/dashboard', 'placeholders.coming-soon', ['feature' => 'dashboard'])
        ->name('dashboard.index');

    // [[settings-profile]] が実装される
    Route::view('/settings/profile', 'placeholders.coming-soon', ['feature' => 'settings-profile'])
        ->name('settings.profile.edit');

    // [[notification]] が実装される
    Route::view('/notifications', 'placeholders.coming-soon', ['feature' => 'notification'])
        ->name('notifications.index');

    // [[certification-management]] 受講生カタログ
    Route::get('certifications', [CertificationCatalogController::class, 'index'])
        ->name('certifications.index');
    Route::get('certifications/{certification}', [CertificationCatalogController::class, 'show'])
        ->name('certifications.show');

    // [[certification-management]] 修了証配信
    Route::get('certificates/{certificate}', [CertificateController::class, 'show'])
        ->name('certificates.show');
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');

    // [[content-management]] 受講生向け教材検索
    Route::get('contents/search', [ContentSearchController::class, 'search'])
        ->name('contents.search');
});

// [[user-management]] admin 専用
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->withTrashed()
        ->name('admin.users.show');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('admin.users.updateRole');
    Route::post('users/{user}/withdraw', [UserController::class, 'withdraw'])->name('admin.users.withdraw');

    Route::post('invitations', [InvitationController::class, 'store'])->name('admin.invitations.store');
    Route::post('users/{user}/resend-invitation', [InvitationController::class, 'resend'])->name('admin.invitations.resend');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('admin.invitations.destroy');

    // [[certification-management]] admin 資格マスタ CRUD + 状態遷移
    Route::resource('certifications', CertificationController::class)
        ->parameters(['certifications' => 'certification'])
        ->names('admin.certifications');
    Route::post('certifications/{certification}/publish', [CertificationController::class, 'publish'])
        ->name('admin.certifications.publish');
    Route::post('certifications/{certification}/archive', [CertificationController::class, 'archive'])
        ->name('admin.certifications.archive');
    Route::post('certifications/{certification}/unarchive', [CertificationController::class, 'unarchive'])
        ->name('admin.certifications.unarchive');

    // [[certification-management]] 担当コーチ割当
    Route::post('certifications/{certification}/coaches', [CertificationCoachAssignmentController::class, 'store'])
        ->name('admin.certifications.coaches.store');
    Route::delete('certifications/{certification}/coaches/{user}', [CertificationCoachAssignmentController::class, 'destroy'])
        ->name('admin.certifications.coaches.destroy');

    // [[certification-management]] 資格分類マスタ
    Route::resource('certification-categories', CertificationCategoryController::class)
        ->parameters(['certification-categories' => 'category'])
        ->except(['show', 'create', 'edit'])
        ->names('admin.certification-categories');
});

// [[content-management]] admin + coach (担当資格のみ Policy で絞り込み)
Route::middleware(['auth', 'role:admin,coach'])->prefix('admin')->group(function () {
    // 資格配下: Part 一覧 / 新規作成 / 並び替え
    Route::get('certifications/{certification}/parts', [PartController::class, 'index'])
        ->name('admin.certifications.parts.index');
    Route::post('certifications/{certification}/parts', [PartController::class, 'store'])
        ->name('admin.certifications.parts.store');
    Route::patch('certifications/{certification}/parts/reorder', [PartController::class, 'reorder'])
        ->name('admin.certifications.parts.reorder');

    // Part: 詳細 / 更新 / 削除 / 公開遷移 / 配下 Chapter 操作
    Route::get('parts/{part}', [PartController::class, 'show'])->name('admin.parts.show');
    Route::patch('parts/{part}', [PartController::class, 'update'])->name('admin.parts.update');
    Route::delete('parts/{part}', [PartController::class, 'destroy'])->name('admin.parts.destroy');
    Route::post('parts/{part}/publish', [PartController::class, 'publish'])->name('admin.parts.publish');
    Route::post('parts/{part}/unpublish', [PartController::class, 'unpublish'])->name('admin.parts.unpublish');
    Route::post('parts/{part}/chapters', [ChapterController::class, 'store'])->name('admin.parts.chapters.store');
    Route::patch('parts/{part}/chapters/reorder', [ChapterController::class, 'reorder'])
        ->name('admin.parts.chapters.reorder');

    // Chapter: 詳細 / 更新 / 削除 / 公開遷移 / 配下 Section 操作
    Route::get('chapters/{chapter}', [ChapterController::class, 'show'])->name('admin.chapters.show');
    Route::patch('chapters/{chapter}', [ChapterController::class, 'update'])->name('admin.chapters.update');
    Route::delete('chapters/{chapter}', [ChapterController::class, 'destroy'])->name('admin.chapters.destroy');
    Route::post('chapters/{chapter}/publish', [ChapterController::class, 'publish'])->name('admin.chapters.publish');
    Route::post('chapters/{chapter}/unpublish', [ChapterController::class, 'unpublish'])->name('admin.chapters.unpublish');
    Route::post('chapters/{chapter}/sections', [SectionController::class, 'store'])
        ->name('admin.chapters.sections.store');
    Route::patch('chapters/{chapter}/sections/reorder', [SectionController::class, 'reorder'])
        ->name('admin.chapters.sections.reorder');

    // Section: 詳細 / 更新 / 削除 / 公開遷移 / Markdown プレビュー / 画像アップロード
    Route::get('sections/{section}', [SectionController::class, 'show'])->name('admin.sections.show');
    Route::patch('sections/{section}', [SectionController::class, 'update'])->name('admin.sections.update');
    Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('admin.sections.destroy');
    Route::post('sections/{section}/publish', [SectionController::class, 'publish'])->name('admin.sections.publish');
    Route::post('sections/{section}/unpublish', [SectionController::class, 'unpublish'])->name('admin.sections.unpublish');
    Route::post('sections/{section}/preview', [SectionController::class, 'preview'])->name('admin.sections.preview');
    Route::post('sections/{section}/images', [SectionImageController::class, 'store'])
        ->name('admin.sections.images.store');

    // SectionImage 削除
    Route::delete('section-images/{image}', [SectionImageController::class, 'destroy'])
        ->name('admin.section-images.destroy');

    // 資格配下: QuestionCategory マスタ CRUD
    Route::get('certifications/{certification}/question-categories', [QuestionCategoryController::class, 'index'])
        ->name('admin.certifications.question-categories.index');
    Route::post('certifications/{certification}/question-categories', [QuestionCategoryController::class, 'store'])
        ->name('admin.certifications.question-categories.store');
    Route::patch('question-categories/{category}', [QuestionCategoryController::class, 'update'])
        ->name('admin.question-categories.update');
    Route::delete('question-categories/{category}', [QuestionCategoryController::class, 'destroy'])
        ->name('admin.question-categories.destroy');

    // 資格配下: Question 一覧 / 作成 / 詳細・編集 / 公開遷移
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

// 開発専用: コンポーネントショーケース (APP_ENV=local のみ表示)
if (app()->environment('local')) {
    Route::get('/_dev/components', function () {
        return view('_dev.components');
    })->name('_dev.components');
}
