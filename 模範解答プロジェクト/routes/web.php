<?php

use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificationCatalogController;
use App\Http\Controllers\CertificationCategoryController;
use App\Http\Controllers\CertificationCoachAssignmentController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\InvitationController;
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

// 開発専用: コンポーネントショーケース (APP_ENV=local のみ表示)
if (app()->environment('local')) {
    Route::get('/_dev/components', function () {
        return view('_dev.components');
    })->name('_dev.components');
}
