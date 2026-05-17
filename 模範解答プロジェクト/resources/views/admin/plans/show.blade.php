@extends('layouts.app')

@section('title', 'プラン詳細 — ' . $plan->name)

@php
    use App\Enums\PlanStatus;

    $statusBadge = match ($plan->status) {
        PlanStatus::Published => ['variant' => 'success', 'label' => '公開中'],
        PlanStatus::Draft => ['variant' => 'warning', 'label' => '下書き'],
        PlanStatus::Archived => ['variant' => 'gray', 'label' => 'アーカイブ'],
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'プラン管理', 'href' => route('admin.plans.index')],
        ['label' => $plan->name],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-ink-900">{{ $plan->name }}</h1>
                <x-badge :variant="$statusBadge['variant']" size="md">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                    {{ $statusBadge['label'] }}
                </x-badge>
            </div>
            @if ($plan->description)
                <p class="text-sm text-ink-600 mt-2 max-w-2xl">{{ $plan->description }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @can('update', $plan)
                <x-link-button href="{{ route('admin.plans.edit', $plan) }}" variant="outline">
                    <x-icon name="pencil-square" class="w-4 h-4" />
                    編集
                </x-link-button>
            @endcan

            @can('publish', $plan)
                @if ($plan->status === PlanStatus::Draft)
                    <form method="POST" action="{{ route('admin.plans.publish', $plan) }}" class="inline-block">
                        @csrf
                        <x-button type="submit" variant="primary">
                            <x-icon name="check-circle" class="w-4 h-4" />
                            公開する
                        </x-button>
                    </form>
                @endif
            @endcan

            @can('archive', $plan)
                @if ($plan->status === PlanStatus::Published)
                    <form method="POST" action="{{ route('admin.plans.archive', $plan) }}" class="inline-block">
                        @csrf
                        <x-button type="submit" variant="secondary">
                            <x-icon name="archive-box" class="w-4 h-4" />
                            アーカイブ
                        </x-button>
                    </form>
                @endif
            @endcan

            @can('unarchive', $plan)
                @if ($plan->status === PlanStatus::Archived)
                    <form method="POST" action="{{ route('admin.plans.unarchive', $plan) }}" class="inline-block">
                        @csrf
                        <x-button type="submit" variant="secondary">
                            <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                            下書きへ戻す
                        </x-button>
                    </form>
                @endif
            @endcan

            @can('delete', $plan)
                <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline-block"
                      onsubmit="return confirm('このプランを削除しますか？(下書きかつ受講者未紐づきの場合のみ削除可能)');">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="danger">
                        <x-icon name="trash" class="w-4 h-4" />
                        削除
                    </x-button>
                </form>
            @endcan
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <x-card padding="md" shadow="sm">
            <x-slot:header>受講期間</x-slot:header>
            <div class="text-3xl font-bold text-ink-900 tabular-nums">{{ number_format($plan->duration_days) }}</div>
            <div class="text-sm text-ink-500">日</div>
        </x-card>

        <x-card padding="md" shadow="sm">
            <x-slot:header>初期付与面談回数</x-slot:header>
            <div class="text-3xl font-bold text-ink-900 tabular-nums">{{ number_format($plan->default_meeting_quota) }}</div>
            <div class="text-sm text-ink-500">回</div>
        </x-card>

        <x-card padding="md" shadow="sm">
            <x-slot:header>受講者数</x-slot:header>
            <div class="text-3xl font-bold text-ink-900 tabular-nums">{{ $plan->users->count() }}</div>
            <div class="text-sm text-ink-500">名</div>
        </x-card>
    </div>

    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>受講者一覧</x-slot:header>

        @if ($plan->users->isEmpty())
            <p class="text-sm text-ink-500 py-4 text-center">このプランを受講中のユーザーはまだいません。</p>
        @else
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>名前</x-table.heading>
                        <x-table.heading>メール</x-table.heading>
                        <x-table.heading>受講期限</x-table.heading>
                        <x-table.heading class="text-right">残面談回数</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($plan->users as $user)
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.users.show', $user) }}" class="text-sm font-medium text-ink-900 hover:text-primary-700">
                                {{ $user->name ?? '—' }}
                            </a>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700 font-mono">{{ $user->email }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700">
                                {{ $user->plan_expires_at?->format('Y-m-d') ?? '—' }}
                            </span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-sm text-ink-700 tabular-nums">{{ $user->max_meetings }}</span>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-card>

    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>メタ情報</x-slot:header>
        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
            <div>
                <dt class="text-ink-500">作成者</dt>
                <dd class="text-ink-900 mt-0.5">{{ $plan->createdBy->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">最終更新者</dt>
                <dd class="text-ink-900 mt-0.5">{{ $plan->updatedBy->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">作成日時</dt>
                <dd class="text-ink-900 mt-0.5">{{ $plan->created_at?->format('Y-m-d H:i') }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">更新日時</dt>
                <dd class="text-ink-900 mt-0.5">{{ $plan->updated_at?->format('Y-m-d H:i') }}</dd>
            </div>
        </dl>
    </x-card>
@endsection
