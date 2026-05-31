<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use Closure;
use Throwable;

/**
 * dashboard 各 Action の例外境界ヘルパー。
 *
 * 個別 Service 例外で画面全体が 500 化するのを防ぐため、各セクション build を本 trait の `safe()` で包む。
 * 例外時は `report()` で記録した上で null を返し、ViewModel プロパティを nullable にして Blade で
 * empty-state 表示に切り替える。
 */
trait HasDashboardSafeFetch
{
    /**
     * `$fn` を実行し、`Throwable` を捕捉した場合は report の上 null を返す。
     */
    private function safe(Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
