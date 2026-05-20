<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard\ViewModels;

use Illuminate\Support\Collection;

/**
 * 管理者ダッシュボード全体の ViewModel。Blade はプロパティアクセスのみで描画する。
 *
 * 全体 KPI(learning / passed / failed)/ 資格別受講中人数(上位 10)/ 資格別修了率を表示する。
 * Service 例外で取得失敗したセクションは nullable プロパティに null が入り、Blade で empty-state にフォールバックする。
 */
final readonly class AdminDashboardViewModel
{
    /**
     * @param  ?array{learning_count: int, passed_count: int, failed_count: int, total: int, by_certification: array<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int}>}  $kpi  全体 KPI(取得失敗時 null)
     * @param  Collection<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int}>  $byCertificationTop10  資格別受講中人数 上位 10 件
     * @param  ?Collection<int, array{certification_id: string, certification_name: string, learning: int, passed: int, failed: int, total: int, completion_rate: float}>  $completionRateByCertification  資格別修了率(取得失敗時 null)
     */
    public function __construct(
        public ?array $kpi,
        public Collection $byCertificationTop10,
        public ?Collection $completionRateByCertification,
        public bool $isEmptyState,
    ) {}
}
