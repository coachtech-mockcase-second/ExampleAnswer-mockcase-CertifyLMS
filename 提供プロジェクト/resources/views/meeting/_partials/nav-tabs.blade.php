{{--
    面談機能の共通ヘッダ。「予約する / 履歴」タブ + 残面談回数 + 追加購入導線。予約・履歴の両画面で共有する。
    props: meetingsRemaining(残面談回数、Controller から渡す)。現在地タブはルート名で自動判定(予約フロー or 履歴)。
--}}
@props(['meetingsRemaining'])

@php
    // 予約フロー(canonical + fallback)にいるかで予約タブ / 履歴タブの現在地を切り替える。
    $onCreate = request()->routeIs('meetings.create', 'meetings.fallback.*');
@endphp

<div class="mt-4 flex items-end justify-between gap-4 flex-wrap border-b border-subtle">
    {{-- 予約 / 履歴 タブ(リンク遷移、JS なし) --}}
    <nav class="flex gap-1 -mb-px" aria-label="面談">
        <a href="{{ route('meetings.fallback.create') }}"
           @if ($onCreate) aria-current="page" @endif
           class="px-4 py-2.5 text-sm font-semibold border-b-2 transition {{ $onCreate ? 'border-primary-600 text-primary-700' : 'border-transparent text-ink-500 hover:text-ink-800 hover:border-ink-300' }}">
            予約する
        </a>
        <a href="{{ route('meetings.index') }}"
           @if (! $onCreate) aria-current="page" @endif
           class="px-4 py-2.5 text-sm font-semibold border-b-2 transition {{ ! $onCreate ? 'border-primary-600 text-primary-700' : 'border-transparent text-ink-500 hover:text-ink-800 hover:border-ink-300' }}">
            履歴
        </a>
    </nav>

    {{-- 残面談回数 + 追加購入導線(常時表示) --}}
    <div class="flex items-center gap-3 pb-2">
        <div class="text-right">
            <span class="text-[10px] uppercase tracking-wider text-ink-500">残り面談回数</span>
            <span class="ml-1.5 font-display text-xl font-bold text-primary-700 tabular-nums">{{ $meetingsRemaining }}</span>
            <span class="text-xs text-ink-500">回</span>
        </div>
        @if (Route::has('meeting-quota.checkout.select'))
            <a href="{{ route('meeting-quota.checkout.select') }}" class="text-xs text-primary-700 hover:underline whitespace-nowrap">
                追加購入 →
            </a>
        @endif
    </div>
</div>
