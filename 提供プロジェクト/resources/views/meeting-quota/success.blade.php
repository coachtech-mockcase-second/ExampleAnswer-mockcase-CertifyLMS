{{--
    追加面談パックの決済完了画面（受講生向け、決済フローからの戻り先）。
    構成: 中央寄せカード（成功アイコン → 完了見出し + 反映遅延の案内文 → 購入内容サマリ → ダッシュボード / 購入履歴への導線）。
    JS なし: 表示専用のリンクのみ。購入内容サマリは決済情報がある場合のみ表示。
--}}
@extends('layouts.app')

@section('title', 'お支払い完了')

@section('content')
    <div class="max-w-xl mx-auto mt-8">
        <x-card padding="lg" shadow="md" class="text-center">
            <div class="mx-auto w-16 h-16 rounded-full bg-success-50 text-success-600 flex items-center justify-center">
                <x-icon name="check-circle" variant="solid" class="w-10 h-10" />
            </div>
            <h1 class="text-xl font-bold text-ink-900 mt-4">お支払いが完了しました</h1>
            <p class="text-sm text-ink-600 mt-2">
                ご購入ありがとうございます。決済が確定次第、残面談回数へ自動的に加算されます。
                反映までに数秒〜数十秒の遅延が生じることがあります。
            </p>

            @if ($payment)
                <dl class="mt-6 grid grid-cols-2 gap-3 text-left text-sm bg-ink-50 rounded-md p-4">
                    <dt class="text-ink-500">プラン</dt>
                    <dd class="text-ink-900">{{ $payment->meetingPack?->name ?? '—' }}</dd>
                    <dt class="text-ink-500">面談回数</dt>
                    <dd class="text-ink-900 tabular-nums">{{ $payment->quantity }} 回</dd>
                    <dt class="text-ink-500">金額</dt>
                    <dd class="text-ink-900 tabular-nums">¥{{ number_format($payment->amount) }}</dd>
                    <dt class="text-ink-500">決済ステータス</dt>
                    <dd class="text-ink-900">{{ $payment->status->label() }}</dd>
                </dl>
            @endif

            <div class="mt-6 flex items-center justify-center gap-2">
                <x-link-button href="{{ route('dashboard.index') }}" variant="primary">
                    <x-icon name="home" class="w-4 h-4" />
                    ダッシュボードへ戻る
                </x-link-button>
                <x-link-button href="{{ route('meeting-quota.history') }}" variant="ghost">
                    購入履歴を見る
                </x-link-button>
            </div>
        </x-card>
    </div>
@endsection
