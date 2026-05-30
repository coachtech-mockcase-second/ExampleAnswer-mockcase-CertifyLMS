{{--
    管理者ダッシュボード画面。プラットフォーム全体の運用状況サマリ。
    構成: ヘッダ(日付 + タイトル + 資格追加 / ユーザー招待ボタン) → 空状態 or [全体 KPI サマリ → 資格別 受講中人数 / 資格別 修了率 の 2 カラム]
    JS なし
--}}
@extends('layouts.app')

@section('title', '管理ダッシュボード')

@section('content')
    <div class="flex flex-wrap justify-between gap-3 mb-6">
        <div>
            <p class="text-xs text-ink-500">{{ now()->format('Y年n月j日') }} ({{ ['日', '月', '火', '水', '木', '金', '土'][now()->dayOfWeek] }})</p>
            <h1 class="font-display text-2xl font-bold text-ink-900 mt-1">管理ダッシュボード</h1>
            <p class="text-sm text-ink-600 mt-1">プラットフォーム全体の運用状況。</p>
        </div>
        <div class="flex gap-2 items-start">
            <x-link-button href="{{ route('admin.certifications.create') }}" variant="outline">
                <x-icon name="academic-cap" class="w-4 h-4 mr-1.5" />
                資格を追加
            </x-link-button>
            <x-link-button href="{{ route('admin.users.index') }}">
                <x-icon name="plus" class="w-4 h-4 mr-1.5" />
                ユーザー招待
            </x-link-button>
        </div>
    </div>

    @if ($viewModel->isEmptyState)
        <x-empty-state
            icon="rocket-launch"
            title="まずはプランを作成してユーザーを招待してください"
            description="プラン・資格マスタを整え、ユーザー招待を発行すると KPI が表示されはじめます。"
        >
            <x-slot:action>
                <div class="flex gap-2 justify-center">
                    <x-link-button href="{{ route('admin.plans.index') }}" variant="outline">プラン管理</x-link-button>
                    <x-link-button href="{{ route('admin.users.index') }}">ユーザー管理</x-link-button>
                </div>
            </x-slot:action>
        </x-empty-state>
    @else
        @include('dashboard._partials.admin.kpi-overview', ['kpi' => $viewModel->kpi])

        <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
            @include('dashboard._partials.admin.by-certification-breakdown', [
                'rows' => $viewModel->byCertificationTop10,
            ])
            @include('dashboard._partials.admin.completion-rate-list', [
                'rows' => $viewModel->completionRateByCertification,
            ])
        </div>
    @endif
@endsection
