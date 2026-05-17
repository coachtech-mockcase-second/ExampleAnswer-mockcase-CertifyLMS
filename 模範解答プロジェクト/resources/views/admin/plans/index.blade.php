@extends('layouts.app')

@section('title', 'プラン管理')

@php
    use App\Enums\PlanStatus;

    $statusBadge = fn (PlanStatus $s) => match ($s) {
        PlanStatus::Published => ['variant' => 'success', 'dot' => true],
        PlanStatus::Draft => ['variant' => 'warning', 'dot' => true],
        PlanStatus::Archived => ['variant' => 'gray', 'dot' => true],
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'プラン管理'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">プラン管理</h1>
            <p class="text-sm text-ink-500 mt-1">
                受講プランの追加・編集・公開状態の管理を行います。
                <span class="font-semibold text-ink-700">{{ $plans->total() }} 件</span>
            </p>
        </div>
        <x-link-button href="{{ route('admin.plans.create') }}" variant="primary">
            <x-icon name="plus" class="w-4 h-4" />
            新規プラン
        </x-link-button>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('admin.plans.index') }}" class="grid gap-3 sm:grid-cols-[1fr_180px_auto]">
            <div class="relative">
                <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
                <input
                    type="search"
                    name="keyword"
                    value="{{ $keyword }}"
                    placeholder="プラン名で検索"
                    maxlength="100"
                    class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
                >
            </div>

            <select
                name="status"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全ステータス</option>
                @foreach (PlanStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <x-button type="submit" variant="primary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    絞り込み
                </x-button>
                @if ($keyword || $status)
                    <x-link-button href="{{ route('admin.plans.index') }}" variant="ghost">クリア</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    {{-- 一覧 --}}
    @if ($plans->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="credit-card"
                    title="該当するプランがありません"
                    description="条件を変えるか、新しくプランを作成してみてください。"
                >
                    <x-slot:action>
                        <x-link-button href="{{ route('admin.plans.create') }}" variant="primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            新規プラン
                        </x-link-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>プラン名</x-table.heading>
                        <x-table.heading class="text-right">受講期間</x-table.heading>
                        <x-table.heading class="text-right">面談回数</x-table.heading>
                        <x-table.heading>ステータス</x-table.heading>
                        <x-table.heading class="text-right">受講者数</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>

                @foreach ($plans as $plan)
                    @php $sb = $statusBadge($plan->status); @endphp
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.plans.show', $plan) }}" class="block group">
                                <div class="text-sm font-semibold text-ink-900 group-hover:text-primary-700 transition-colors">{{ $plan->name }}</div>
                                @if ($plan->description)
                                    <div class="text-xs text-ink-500 mt-0.5 line-clamp-1">{{ $plan->description }}</div>
                                @endif
                            </a>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-sm text-ink-700 tabular-nums">{{ number_format($plan->duration_days) }} 日</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-sm text-ink-700 tabular-nums">{{ number_format($plan->default_meeting_quota) }} 回</span>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$sb['variant']" size="sm">
                                @if ($sb['dot'])
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                                @endif
                                {{ $plan->status->label() }}
                            </x-badge>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-xs text-ink-500 font-mono tabular-nums">{{ $plan->users_count ?? 0 }} 名</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('admin.plans.show', $plan) }}" variant="ghost" size="sm">
                                <x-icon name="eye" class="w-4 h-4" />
                                詳細
                            </x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$plans" />
        </div>
    @endif
@endsection
