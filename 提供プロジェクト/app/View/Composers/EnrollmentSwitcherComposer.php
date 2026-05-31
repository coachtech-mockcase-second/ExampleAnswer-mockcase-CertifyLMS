<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * 資格スイッチャー(<x-enrollment-switcher>)に表示する受講中資格一覧を注入する View Composer。
 *
 * Blade コンポーネント内で直接クエリを組み立てるのを避け、データ取得を 1 箇所に集約する。
 * 実クエリは User::switchableEnrollments リレーションが担い、同一リクエスト内で複数描画されても
 * リレーションキャッシュにより 1 回だけ実行される。受講生以外の画面ではコンポーネント自体が描画されない。
 */
final class EnrollmentSwitcherComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        $view->with('switcherEnrollments', $user?->switchableEnrollments ?? collect());
    }
}
