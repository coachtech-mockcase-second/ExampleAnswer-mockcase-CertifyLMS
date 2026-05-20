<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard\ViewModels;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * 卒業済ユーザー向けの縮減ダッシュボード ViewModel。
 *
 * `User.status === UserStatus::Graduated` のユーザーがログインした時のみ使用される。
 * 中核は「修了済資格一覧 + PDF DL リンク」のみ(プラン機能 / プロフィール閲覧 / 卒業日表示は持たない、サイドバーから直接アクセス)。
 */
final readonly class GraduatedDashboardViewModel
{
    /**
     * @param  EloquentCollection<int, \App\Models\Enrollment>  $passedEnrollments  修了済 Enrollment(passed_at DESC、Certificate eager load 済)
     */
    public function __construct(
        public EloquentCollection $passedEnrollments,
    ) {}
}
