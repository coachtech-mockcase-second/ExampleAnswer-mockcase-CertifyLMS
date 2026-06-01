{{--
    面談回数の増減履歴画面（受講生向け）。
    構成: 見出し + 残回数表示 + 購入ボタン → 種別絞り込みフォーム → 履歴テーブル（0 件時は空状態）→ ページネーション。
    テーブルは発生日時 / 種別バッジ / 増減回数（プラスは緑・マイナスは赤）/ 備考。
    JS なし: 絞り込みは GET フォーム送信、購入ボタンは購入選択画面へリンク遷移。
--}}
@extends('layouts.app')

@section('title', '面談回数履歴')

@php
    use App\Enums\MeetingQuotaTransactionType;

    $typeBadge = fn (MeetingQuotaTransactionType $t) => match ($t) {
        MeetingQuotaTransactionType::GrantedInitial => ['variant' => 'info'],
        MeetingQuotaTransactionType::Purchased => ['variant' => 'success'],
        MeetingQuotaTransactionType::AdminGrant => ['variant' => 'success'],
        MeetingQuotaTransactionType::Refunded => ['variant' => 'gray'],
        MeetingQuotaTransactionType::Consumed => ['variant' => 'warning'],
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談回数履歴'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">面談回数履歴</h1>
            <p class="text-sm text-ink-500 mt-1">
                残面談回数:
                <span class="font-semibold text-ink-900 tabular-nums">{{ $remaining }} 回</span>
            </p>
        </div>
        <x-link-button href="{{ route('meeting-quota.checkout.select') }}" variant="primary">
            <x-icon name="plus" class="w-4 h-4" />
            追加面談を購入
        </x-link-button>
    </div>

    <x-card class="mt-6" padding="sm" shadow="sm">
        <form novalidate method="GET" action="{{ route('meeting-quota.history') }}" class="flex items-center gap-2 flex-wrap">
            <select
                name="type"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全種別</option>
                @foreach (MeetingQuotaTransactionType::cases() as $t)
                    <option value="{{ $t->value }}" @selected($type === $t->value)>{{ $t->label() }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="primary">
                <x-icon name="funnel" class="w-4 h-4" />
                絞り込み
            </x-button>
            @if ($type)
                <x-link-button href="{{ route('meeting-quota.history') }}" variant="ghost">クリア</x-link-button>
            @endif
        </form>
    </x-card>

    @if ($transactions->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="clock"
                    title="該当する履歴はありません"
                    description="絞り込み条件を変えてもう一度お試しください。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>発生日時</x-table.heading>
                        <x-table.heading>種別</x-table.heading>
                        <x-table.heading class="text-right">回数</x-table.heading>
                        <x-table.heading>備考</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($transactions as $tx)
                    @php $tb = $typeBadge($tx->type); @endphp
                    <x-table.row>
                        <x-table.cell>
                            <span class="text-sm text-ink-700 tabular-nums">{{ $tx->occurred_at?->format('Y-m-d H:i') }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$tb['variant']" size="sm">{{ $tx->type->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-sm font-semibold tabular-nums {{ $tx->amount < 0 ? 'text-danger-700' : 'text-success-700' }}">
                                {{ $tx->amount > 0 ? '+' : '' }}{{ $tx->amount }} 回
                            </span>
                        </x-table.cell>
                        <x-table.cell>
                            <div class="text-sm text-ink-700">
                                @if (class_exists(\App\Models\Payment::class) && $tx->relatedPayment)
                                    {{ $tx->relatedPayment->meetingPack?->name ?? '—' }}
                                @elseif ($tx->note)
                                    {{ $tx->note }}
                                @else
                                    —
                                @endif
                            </div>
                            @if ($tx->grantedBy)
                                <div class="text-xs text-ink-500 mt-0.5">付与: {{ $tx->grantedBy->name }}</div>
                            @endif
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$transactions" />
        </div>
    @endif
@endsection
