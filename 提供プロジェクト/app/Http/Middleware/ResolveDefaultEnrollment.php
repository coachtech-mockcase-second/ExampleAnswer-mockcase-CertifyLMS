<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\EnrollmentStatus;
use App\Services\DefaultEnrollmentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 受講生のデフォルト資格を解決し、教材 / 模試 / 面談予約画面の 1 階層目を 2 階層目(/.../enrollments/{default_id})へ
 * 302 redirect する Middleware。
 *
 * 使用例: Route::middleware('resolve-default-enrollment:learning.enrollments.show')->get('/learning', ...);
 *
 * 判定フロー:
 * - URL に {enrollment} Route パラメータが既に含まれている: スキップ(Controller に委譲)
 * - default_enrollment_id が無効(参照先 SoftDelete / failed): clearIfInvalid で NULL リセット → 後段判定
 * - default が有効: 引数の routeName + {enrollment} で 302 redirect
 * - default NULL かつ 残存 learning|passed Enrollment ちょうど 1 件: その 1 件に自動 redirect(users 更新なし)
 * - default NULL かつ 残存 0 件 or 2+ 件: Controller に委譲(フォールバック UI 表示は Controller 責務)
 */
class ResolveDefaultEnrollment
{
    public function __construct(private readonly DefaultEnrollmentService $resolver) {}

    public function handle(Request $request, Closure $next, string $routeName): Response
    {
        if ($request->route('enrollment') !== null) {
            return $next($request);
        }

        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->default_enrollment_id !== null) {
            $this->resolver->clearIfInvalid($user);
            $user->refresh();
        }

        if ($user->default_enrollment_id !== null) {
            return redirect()->route($routeName, ['enrollment' => $user->default_enrollment_id]);
        }

        $activeEnrollments = $user->enrollments()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->get();

        if ($activeEnrollments->count() === 1) {
            return redirect()->route($routeName, ['enrollment' => $activeEnrollments->first()->id]);
        }

        return $next($request);
    }
}
