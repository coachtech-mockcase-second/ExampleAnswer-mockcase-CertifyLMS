<?php

declare(strict_types=1);

namespace App\UseCases\User;

use App\Http\Controllers\UserController;
use App\Models\Plan;
use App\Models\User;
use App\UseCases\Plan\ExtendCourseAction as PlanExtendCourseAction;

/**
 * 管理者操作のユーザー詳細画面「プラン延長」(`UserController::extendCourse`)から呼ばれるラッパー Action。
 *
 * Plan 期間 + 面談付与回数の延長そのものは Plan ドメインの `\App\UseCases\Plan\ExtendCourseAction` が担う。
 * 本ラッパーは「Controller method 名 = 同 Feature の Action クラス名」規約を保つために配置する。
 *
 * @see UserController::extendCourse()
 */
final class ExtendCourseAction
{
    public function __construct(
        private readonly PlanExtendCourseAction $extend,
    ) {}

    public function __invoke(User $user, Plan $plan, User $admin): User
    {
        return ($this->extend)($user, $plan, $admin, 'プラン延長');
    }
}
