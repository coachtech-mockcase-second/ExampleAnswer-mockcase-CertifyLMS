<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\DashboardController;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Dashboard\ViewModels\GraduatedDashboardViewModel;

/**
 * 卒業済ユーザー向け縮減ダッシュボードの ViewModel を組み立てる Action。
 *
 * `User.status === UserStatus::Graduated` のユーザーが対象。修了済資格一覧 + PDF DL リンクのみを集約する。
 * プラン機能 / プロフィール閲覧 / 卒業日表示は持たない(サイドバーから直接アクセス)。
 *
 * @see DashboardController::index()
 */
final class FetchGraduatedDashboardAction
{
    public function __invoke(User $graduated): GraduatedDashboardViewModel
    {
        $passedEnrollments = Enrollment::query()
            ->where('user_id', $graduated->id)
            ->where('status', EnrollmentStatus::Passed)
            ->whereNotNull('passed_at')
            ->with(['certification', 'certificate'])
            ->orderByDesc('passed_at')
            ->get();

        return new GraduatedDashboardViewModel(
            passedEnrollments: $passedEnrollments,
        );
    }
}
