{{--
    管理者ダッシュボードの全体 KPI サマリバンド。
    構成: フォールバック表示 or KPI タイル 3 枚(受講中[強調] / 修了 / 学習中止)
    props: kpi（サマリ値の組）
--}}
@props([
    'kpi',
])

@if ($kpi === null)
    @include('dashboard._partials.empty-state', ['message' => '全体 KPI を取得できませんでした。'])
@else
    <div class="grid gap-3.5 mb-6 sm:grid-cols-1 lg:grid-cols-3">
        @include('dashboard._partials.kpi-tile', [
            'icon' => 'users',
            'label' => '受講中',
            'value' => $kpi['learning_count'],
            'featured' => true,
        ])
        @include('dashboard._partials.kpi-tile', [
            'icon' => 'check-badge',
            'iconColor' => 'text-success-600',
            'valueColor' => 'text-success-700',
            'label' => '修了',
            'value' => $kpi['passed_count'],
            'delta' => '累計 ' . ($kpi['learning_count'] + $kpi['passed_count'] + $kpi['failed_count']) . ' 名',
        ])
        @include('dashboard._partials.kpi-tile', [
            'icon' => 'x-circle',
            'iconColor' => 'text-danger-600',
            'valueColor' => 'text-danger-700',
            'label' => '学習中止',
            'value' => $kpi['failed_count'],
        ])
    </div>
@endif
