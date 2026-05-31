<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\UseCases\Dashboard\FetchAdminDashboardAction;
use App\UseCases\Dashboard\FetchCoachDashboardAction;
use App\UseCases\Dashboard\FetchGraduatedDashboardAction;
use App\UseCases\Dashboard\FetchStudentDashboardAction;
use Illuminate\View\View;

/**
 * ログイン直後の `/dashboard` を提供する Controller。
 *
 * ロール / ステータス分岐:
 * - `user.status === UserStatus::Graduated` → graduated 専用ダッシュボード(修了済資格一覧のみ)
 * - `user.role === UserRole::Admin` → admin ダッシュボード(全体 KPI + 資格別 + 修了率)
 * - `user.role === UserRole::Coach` → coach ダッシュボード(担当受講生 + 面談 + chat + QA + 通知)
 * - `user.role === UserRole::Student` → student ダッシュボード(プラン情報 + 受講中資格 + 修了済 + ストリーク + 目標 + 通知 + 面談)
 *
 * 本 Controller は読み取り専用集約 Controller のため、Policy は持たず `auth` middleware のみで認可する。
 *
 * 命名規約の意図的な例外: 通常 `Controller method 名 = Action クラス名` を採用するが、本 Controller は
 * 単一ルート `dashboard.index` に対して role × status の状態で 4 つの完全に異なる集約パスを内部分岐させる
 * 集約点として設計されている(ロールごとに必要な Service / Eloquent の組み合わせ + ViewModel 形状が大きく異なる
 * ため、1 Action で if 分岐させると責務が肥大化する)。`Fetch{Role}DashboardAction` の 4 分割は意図的設計。
 */
class DashboardController extends Controller
{
    public function index(
        FetchAdminDashboardAction $fetchAdmin,
        FetchCoachDashboardAction $fetchCoach,
        FetchStudentDashboardAction $fetchStudent,
        FetchGraduatedDashboardAction $fetchGraduated,
    ): View {
        $user = auth()->user();

        if ($user->status === UserStatus::Graduated) {
            $viewModel = $fetchGraduated($user);

            return view('dashboard.graduated', ['viewModel' => $viewModel]);
        }

        $viewModel = match ($user->role) {
            UserRole::Admin => $fetchAdmin($user),
            UserRole::Coach => $fetchCoach($user),
            UserRole::Student => $fetchStudent($user),
        };

        return view('dashboard.'.$user->role->value, ['viewModel' => $viewModel]);
    }
}
