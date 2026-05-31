<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SectionQuestionAttempt;
use App\Models\User;

/**
 * SectionQuestionAttempt(受講生 × 問題ごとの累計サマリ)の認可ポリシー。
 *
 * - view: 本人のみ閲覧可
 * 集計値を coach / admin が閲覧する場合は dashboard / enrollment 経由で
 * SectionQuestionAttemptStatsService を呼ぶため、本 Policy は他ロールを許可しない。
 */
class SectionQuestionAttemptPolicy
{
    public function view(User $auth, SectionQuestionAttempt $attempt): bool
    {
        return $auth->id === $attempt->user_id;
    }
}
