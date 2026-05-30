{{--
    管理者ダッシュボードの資格別 修了率カード。
    構成: 見出し → フォールバック / 空文 or 行リスト(資格名 + 修了率バー + パーセント、低水準は警告配色) → 注記文
    props: rows（資格ごとの修了率の行）
--}}
@props([
    'rows',
])

<x-card padding="md">
    <div class="flex items-baseline gap-2 mb-3">
        <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
            <x-icon name="check-badge" class="w-4 h-4 text-success-600" />
            資格別 修了率
        </h2>
    </div>

    @if ($rows === null)
        @include('dashboard._partials.empty-state', ['message' => '修了率を取得できませんでした。'])
    @elseif ($rows->isEmpty())
        <p class="text-sm text-ink-500 py-2">受講登録がまだないため、修了率を集計できません。</p>
    @else
        <ul class="flex flex-col">
            @foreach ($rows as $row)
                @php
                    $rate = $row['completion_rate'];
                    $ratePct = (int) round($rate * 100);
                    $useWarning = $rate < 0.7;
                @endphp
                <li class="grid items-center gap-3 py-2.5 border-b border-subtle last:border-b-0"
                    style="grid-template-columns: 1fr auto auto;">
                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $row['certification_name'] }}</p>
                    <div class="w-24 h-2 bg-ink-100 rounded-full overflow-hidden">
                        <div class="h-full {{ $useWarning ? 'bg-warning-500' : 'bg-success-500' }} rounded-full"
                             style="width: {{ $ratePct }}%"></div>
                    </div>
                    <span class="font-display text-base font-bold tabular-nums tracking-tight min-w-[44px] text-right {{ $useWarning ? 'text-warning-700' : 'text-success-700' }}">
                        {{ $ratePct }}%
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="text-[11px] text-ink-500 mt-3 pt-2.5 border-t border-subtle flex items-start gap-1.5">
            <x-icon name="information-circle" class="w-3 h-3 mt-0.5 flex-shrink-0" />
            <span>修了率 = 修了件数 / 学習中・修了・学習中止の合計。</span>
        </p>
    @endif
</x-card>
