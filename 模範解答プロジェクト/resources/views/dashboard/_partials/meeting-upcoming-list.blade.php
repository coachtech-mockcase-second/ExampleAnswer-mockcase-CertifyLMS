{{--
    今後の面談予定リストの本体（ダッシュボード共通 partial、受講生 / コーチ両方で利用）。
    見出し・予約導線・カード枠は呼び出し側が用意し、ここは中身だけを描画する。
    構成: 直近面談の行リスト（日付チップ + 資格名 + 相手 + 時間帯）。0 件は空状態文。
    props: meetings / partnerAttribute(coach|student)。
--}}
@props([
    'meetings',
    'partnerAttribute' => 'coach',
])

@if ($meetings === null)
    @include('dashboard._partials.empty-state', ['message' => '今後の面談予定を取得できませんでした。'])
@elseif ($meetings->isEmpty())
    <p class="text-sm text-ink-500 py-2">予定された面談はありません。</p>
@else
    <ul class="flex flex-col">
        @foreach ($meetings as $meeting)
            @php
                $partner = $meeting->{$partnerAttribute};
                $scheduled = $meeting->scheduled_at;
            @endphp
            <li class="flex items-center gap-3 py-2.5 border-b border-subtle last:border-b-0">
                <div class="flex-shrink-0 w-12 text-center bg-primary-50 text-primary-800 rounded-lg p-1.5">
                    <div class="font-display text-lg font-extrabold leading-none tabular-nums tracking-tight">{{ $scheduled->format('j') }}</div>
                    <div class="text-[9px] font-semibold mt-0.5">{{ $scheduled->format('n月') }} {{ ['日','月','火','水','木','金','土'][$scheduled->dayOfWeek] }}</div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-ink-900 truncate">{{ $meeting->enrollment?->certification?->name ?? '面談' }}</p>
                    <p class="text-[11px] text-ink-500 mt-0.5 truncate">{{ $partner?->name }}</p>
                </div>
                <div class="font-mono text-[11px] text-ink-700 text-right flex-shrink-0">
                    {{ $scheduled->format('H:i') }}<br>
                    {{ $scheduled->copy()->addMinutes(30)->format('H:i') }}
                </div>
            </li>
        @endforeach
    </ul>
@endif
