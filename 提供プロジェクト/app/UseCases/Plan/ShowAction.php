<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

/**
 * Plan 詳細取得ユースケース。createdBy / updatedBy / 紐づく User 一覧を Eager Load する。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan->load(['createdBy', 'updatedBy', 'users']);
    }
}
