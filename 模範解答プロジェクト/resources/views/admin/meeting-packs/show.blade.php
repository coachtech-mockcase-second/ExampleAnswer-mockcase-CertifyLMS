@extends('layouts.app')

@section('title', '面談パック詳細 — ' . $plan->name)

@php
    use App\Enums\MeetingPackStatus;
    use App\Enums\PaymentStatus;

    $statusBadge = match ($plan->status) {
        MeetingPackStatus::Published => ['variant' => 'success', 'label' => '公開中'],
        MeetingPackStatus::Draft => ['variant' => 'warning', 'label' => '下書き'],
        MeetingPackStatus::Archived => ['variant' => 'gray', 'label' => 'アーカイブ'],
    };

    $paymentBadge = fn (PaymentStatus $s) => match ($s) {
        PaymentStatus::Succeeded => ['variant' => 'success'],
        PaymentStatus::Pending => ['variant' => 'warning'],
        PaymentStatus::Failed => ['variant' => 'danger'],
        PaymentStatus::Refunded => ['variant' => 'gray'],
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談パック管理', 'href' => route('admin.meeting-packs.index')],
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
                <x-link-button href="{{ route('admin.meeting-packs.edit', $plan) }}" variant="outline">
                    <x-icon name="pencil-square" class="w-4 h-4" />
                    編集
                </x-link-button>
            @endcan

            @can('publish', $plan)
                @if ($plan->status === MeetingPackStatus::Draft)
                    <form method="POST" action="{{ route('admin.meeting-packs.publish', $plan) }}" class="inline-block">
                        @csrf
                        <x-button type="submit" variant="primary">
                            <x-icon name="check-circle" class="w-4 h-4" />
                            公開する
                        </x-button>
                    </form>
                @endif
            @endcan

            @can('archive', $plan)
                @if ($plan->status === MeetingPackStatus::Published)
                    <form method="POST" action="{{ route('admin.meeting-packs.archive', $plan) }}" class="inline-block">
                        @csrf
                        <x-button type="submit" variant="secondary">
                            <x-icon name="archive-box" class="w-4 h-4" />
                            アーカイブ
                        </x-button>
                    </form>
                @endif
            @endcan

            @can('unarchive', $plan)
                @if ($plan->status === MeetingPackStatus::Archived)
                    <form method="POST" action="{{ route('admin.meeting-packs.unarchive', $plan) }}" class="inline-block">
                        @csrf
                        <x-button type="submit" variant="secondary">
                            <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                            下書きへ戻す
                        </x-button>
                    </form>
                @endif
            @endcan

            @can('delete', $plan)
                @if ($plan->status !== MeetingPackStatus::Published)
                    <form method="POST" action="{{ route('admin.meeting-packs.destroy', $plan) }}" class="inline-block"
                          onsubmit="return confirm('この面談パックを削除しますか？(公開中は削除できません)');">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger">
                            <x-icon name="trash" class="w-4 h-4" />
                            削除
                        </x-button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <x-card padding="md" shadow="sm">
            <x-slot:header>面談回数</x-slot:header>
            <div class="text-3xl font-bold text-ink-900 tabular-nums">{{ number_format($plan->meeting_count) }}</div>
            <div class="text-sm text-ink-500">回</div>
        </x-card>

        <x-card padding="md" shadow="sm">
            <x-slot:header>価格</x-slot:header>
            <div class="text-3xl font-bold text-ink-900 tabular-nums">¥{{ number_format($plan->price) }}</div>
            <div class="text-sm text-ink-500">税込</div>
        </x-card>

        <x-card padding="md" shadow="sm">
            <x-slot:header>購入数</x-slot:header>
            <div class="text-3xl font-bold text-ink-900 tabular-nums">{{ $plan->payments->count() }}</div>
            <div class="text-sm text-ink-500">件(直近 20 件のみ表示)</div>
        </x-card>
    </div>

    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>購入履歴(直近 20 件)</x-slot:header>

        @if ($plan->payments->isEmpty())
            <p class="text-sm text-ink-500 py-4 text-center">この SKU の購入はまだありません。</p>
        @else
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>購入者</x-table.heading>
                        <x-table.heading class="text-right">金額</x-table.heading>
                        <x-table.heading class="text-right">回数</x-table.heading>
                        <x-table.heading>ステータス</x-table.heading>
                        <x-table.heading>決済日時</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($plan->payments as $payment)
                    @php $pb = $paymentBadge($payment->status); @endphp
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.users.show', $payment->user_id) }}" class="text-sm font-medium text-ink-900 hover:text-primary-700">
                                {{ $payment->user->name ?? '—' }}
                            </a>
                            <div class="text-xs text-ink-500">{{ $payment->user->email ?? '' }}</div>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-sm text-ink-900 tabular-nums">¥{{ number_format($payment->amount) }}</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-sm text-ink-700 tabular-nums">{{ $payment->quantity }} 回</span>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$pb['variant']" size="sm">{{ $payment->status->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700">
                                {{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}
                            </span>
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
                <dt class="text-ink-500">Stripe Price ID</dt>
                <dd class="text-ink-900 mt-0.5 font-mono text-xs">{{ $plan->stripe_price_id ?? '— (動的生成)' }}</dd>
            </div>
            <div>
                <dt class="text-ink-500">並び順</dt>
                <dd class="text-ink-900 mt-0.5 tabular-nums">{{ $plan->sort_order }}</dd>
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
