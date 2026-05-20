@props([
    'panel',
])

@php
    /** @var ?\App\UseCases\Dashboard\ViewModels\PlanInfoPanel $panel */
    $isOutOfMeetings = $panel !== null && $panel->meetingsRemaining === 0;
    $gradientClass = $isOutOfMeetings
        ? 'from-warning-200 via-warning-300 to-warning-400'
        : 'from-warning-200 via-success-200 to-primary-200';
@endphp

@if ($panel === null)
    @include('dashboard._partials.empty-state', ['message' => 'プラン情報を取得できませんでした。'])
@else
    <section class="rounded-3xl px-6 py-5 mb-5 grid gap-5 items-center bg-gradient-to-br {{ $gradientClass }} text-ink-900"
             style="grid-template-columns: 1fr 1fr auto;">
        <div class="flex flex-col">
            <span class="text-[10px] font-bold uppercase tracking-wider text-ink-900/65">現在のプラン</span>
            <span class="font-display font-bold text-lg leading-tight tracking-tight mt-0.5">{{ $panel->planName ?? '未割当' }}</span>
            <div class="font-display tabular-nums tracking-tight mt-1.5">
                @if ($panel->courseDaysRemaining === null)
                    <span class="text-sm font-semibold text-ink-900/70">プラン未割当</span>
                @elseif ($panel->courseDaysRemaining < 0)
                    <span class="text-sm font-semibold text-ink-900/70">期限なし</span>
                @elseif ($panel->courseDaysRemaining === 0)
                    <span class="text-2xl font-extrabold text-danger-700">期限切れ</span>
                @else
                    <span class="text-3xl font-extrabold">{{ $panel->courseDaysRemaining }}</span>
                    <span class="text-sm font-semibold opacity-70 ml-0.5">日 残</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col">
            <span class="text-[10px] font-bold uppercase tracking-wider text-ink-900/65">残面談回数</span>
            <div class="font-display tabular-nums tracking-tight mt-1.5">
                <span class="text-3xl font-extrabold {{ $isOutOfMeetings ? 'text-danger-700' : '' }}">{{ $panel->meetingsRemaining }}</span>
                <span class="text-sm font-semibold opacity-70 ml-0.5">回</span>
            </div>
            <span class="text-[11px] text-ink-900/70 mt-1">{{ $isOutOfMeetings ? '購入で追加可能です。' : '面談予約時に消費されます。' }}</span>
        </div>

        <div class="flex flex-col gap-1.5 items-end">
            @if ($panel->meetingPacks->isNotEmpty())
                <a href="{{ route('meeting-quota.checkout.select') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white text-primary-700 rounded-[10px] text-sm font-bold shadow-sm hover:bg-primary-50 hover:-translate-y-0.5 transition-all duration-fast">
                    <x-icon name="plus" class="w-3.5 h-3.5" />
                    面談回数を購入
                </a>
                <span class="text-[11px] text-ink-900/70">{{ $panel->meetingPacks->count() }} 種類のプラン</span>
            @else
                <span class="text-[11px] text-ink-900/70">面談パックは現在用意されていません。</span>
            @endif
        </div>
    </section>
@endif
