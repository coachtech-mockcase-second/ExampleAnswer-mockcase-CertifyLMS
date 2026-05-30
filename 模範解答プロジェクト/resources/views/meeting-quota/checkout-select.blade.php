{{--
    追加面談パックの購入選択画面（受講生向け）。
    構成: 見出し + 履歴へのリンク → 購入可能パックのカードグリッド（0 件時は空状態）→ 決済に関する補足文。
    各カードはパック名 / 説明 / 価格 + 「このパックを購入」フォーム。
    JS なし: パック選択は隠しフィールド付きフォーム POST で決済フローへ遷移。
--}}
@extends('layouts.app')

@section('title', '追加面談の購入')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '追加面談の購入'],
    ]" />

    <div class="mt-4 flex items-baseline justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">追加面談の購入</h1>
            <p class="text-sm text-ink-500 mt-1">
                希望のパックを選んで決済画面へ進んでください。決済完了で残面談回数が即時加算されます。
            </p>
        </div>
        <a href="{{ route('meeting-quota.history') }}" class="text-sm text-primary-700 hover:underline shrink-0 whitespace-nowrap">面談回数履歴 &rarr;</a>
    </div>

    @if ($plans->isEmpty())
        <x-card class="mt-6" padding="none">
            <x-empty-state
                icon="banknotes"
                title="現在ご購入いただける面談パックはありません"
                description="管理者へお問い合わせください。"
            />
        </x-card>
    @else
        <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <x-card padding="lg" shadow="md" class="flex flex-col">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 class="text-lg font-bold text-ink-900">{{ $plan->name }}</h2>
                        <span class="text-xs text-ink-500 tabular-nums">{{ $plan->meeting_count }} 回</span>
                    </div>
                    @if ($plan->description)
                        <p class="text-sm text-ink-600 mt-2 line-clamp-3">{{ $plan->description }}</p>
                    @endif

                    <div class="mt-6 flex items-baseline gap-1">
                        <span class="text-3xl font-bold text-ink-900 tabular-nums">¥{{ number_format($plan->price) }}</span>
                        <span class="text-sm text-ink-500">/ {{ $plan->meeting_count }} 回</span>
                    </div>

                    <form method="POST" action="{{ route('meeting-quota.checkout.create') }}" class="mt-auto pt-6">
                        @csrf
                        <input type="hidden" name="meeting_pack_id" value="{{ $plan->id }}">
                        <x-button type="submit" variant="primary" class="w-full justify-center">
                            <x-icon name="credit-card" class="w-4 h-4" />
                            このパックを購入
                        </x-button>
                    </form>
                </x-card>
            @endforeach
        </div>

        <p class="text-xs text-ink-500 mt-6">
            決済処理は Stripe で安全に行われます。決済完了後、自動的に残面談回数へ反映されます(数秒〜数十秒の遅延が生じる場合があります)。
        </p>
    @endif
@endsection
