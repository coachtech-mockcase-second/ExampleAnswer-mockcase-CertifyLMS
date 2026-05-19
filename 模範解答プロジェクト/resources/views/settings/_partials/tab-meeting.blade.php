@php
    use Illuminate\Support\Carbon;

    /** @var \App\Models\User $user */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\CoachAvailability> $availabilities */
    $credential = $user->googleCredential;

    $dayLabels = [
        0 => '日',
        1 => '月',
        2 => '火',
        3 => '水',
        4 => '木',
        5 => '金',
        6 => '土',
    ];

    // 表示時間帯: 06:00 〜 22:00 を 1 時間刻みで描画(17 行 × 48px)。
    // 30 分単位の保存値は許容するが、ブロック表示は 1 時間境界に丸めて配置する(精度は表示テキストで示す)。
    $hourStart = 6;
    $hourEnd = 22;
    $hoursCount = $hourEnd - $hourStart;
    $rowHeightPx = 48; // 1 行 = 1 時間 = 48px

    // 同曜日で end_time == start_time となる連続枠を 1 つの表示ブロックにマージ。
    // クリック時には先頭の枠を編集モーダルへ繋ぐ。マージされた他の枠の編集動線は「面談設定タブ全体の編集モーダル一覧」から辿る(現実装はシンプル化)。
    $byDay = [];
    foreach ($availabilities as $a) {
        $byDay[$a->day_of_week][] = $a;
    }
    $blocksByDay = [];
    foreach ($byDay as $dow => $rows) {
        usort($rows, fn ($x, $y) => strcmp((string) $x->start_time, (string) $y->start_time));
        $current = null;
        foreach ($rows as $row) {
            $startStr = Carbon::parse($row->start_time)->format('H:i');
            $endStr = Carbon::parse($row->end_time)->format('H:i');
            if ($current !== null
                && $current['is_active'] === (bool) $row->is_active
                && $current['end'] === $startStr) {
                $current['end'] = $endStr;
                $current['ids'][] = $row->id;
            } else {
                if ($current !== null) {
                    $blocksByDay[$dow][] = $current;
                }
                $current = [
                    'ids' => [$row->id],
                    'start' => $startStr,
                    'end' => $endStr,
                    'is_active' => (bool) $row->is_active,
                ];
            }
        }
        if ($current !== null) {
            $blocksByDay[$dow][] = $current;
        }
    }

    /**
     * 「HH:MM」を 1 時間粒度の grid-row に丸める。先頭行(grid-row=1)は曜日ヘッダ。
     * 開始時刻は floor(切り捨て)、終了時刻は ceil(切り上げ)で扱うことで、
     * 30 分単位の保存値も視覚的に「1 時間枠」として欠落なく表示する。
     */
    $timeToGridRow = function (string $hhmm, bool $ceil) use ($hourStart, $hourEnd): int {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $clamped = max($hourStart, min($hourEnd, $h + $m / 60));
        $hourOffset = $ceil ? (int) ceil($clamped - $hourStart) : (int) floor($clamped - $hourStart);

        return 2 + $hourOffset;
    };
@endphp

<div class="space-y-6">
    {{-- Google カレンダー連携セクション --}}
    <x-card padding="md" shadow="sm">
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-ink-900">Googleカレンダー連携</h2>
                @if ($credential)
                    <x-badge variant="success" size="sm">
                        <x-icon name="check-circle" class="w-3.5 h-3.5" />
                        連携中
                    </x-badge>
                @else
                    <x-badge variant="gray" size="sm">未連携</x-badge>
                @endif
            </div>
        </x-slot:header>

        @if ($credential)
            <div class="space-y-3">
                <div class="rounded-xl bg-success-50 border border-success-200 px-4 py-3 flex items-center gap-3">
                    <x-icon name="calendar-days" class="w-5 h-5 text-success-700 shrink-0" />
                    <div class="min-w-0 text-sm">
                        <div class="font-semibold text-success-900">
                            連携中: <span class="font-mono">{{ $credential->calendar_id }}</span>
                        </div>
                        <div class="text-xs text-success-700 mt-0.5">
                            連携日時: <span class="tabular-nums">{{ $credential->connected_at?->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-ink-500">
                    連携を解除すると、以降の面談予約は Google カレンダーへ自動登録されなくなります。
                    既存の予約は LMS 内には残り、Google 側のイベントは削除されません。
                </p>

                <form method="POST" action="{{ route('settings.google-calendar.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="outline" size="sm">
                        <x-icon name="x-mark" class="w-4 h-4" />
                        連携を解除する
                    </x-button>
                </form>
            </div>
        @else
            <div class="space-y-3">
                <div class="rounded-xl bg-ink-50 border border-[var(--border-subtle)] px-4 py-3 text-sm text-ink-700">
                    Googleカレンダーと連携すると、面談予約が自動でカレンダーに登録され、
                    個人予定の入っている時間帯は受講生の予約画面から自動的に除外されます。
                </div>

                <x-link-button
                    href="{{ route('settings.google-calendar.redirect', ['redirect_path' => '/settings/profile?tab=meeting']) }}"
                    variant="primary"
                    size="sm"
                >
                    <x-icon name="calendar-days" class="w-4 h-4" />
                    Googleカレンダーと連携する
                </x-link-button>
            </div>
        @endif
    </x-card>

    {{-- 面談可能時間枠カレンダー --}}
    <x-card padding="md" shadow="sm">
        <x-slot:header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-ink-900">面談可能時間枠</h2>
                    <p class="text-xs text-ink-500 mt-1">
                        受講生があなたとの面談を予約できる曜日と時間帯を、カレンダー上で直接登録します。
                        空白セルをクリックで新規追加、ドラッグで複数スロットを選択、登録済の色付きブロックをクリックで編集できます。
                    </p>
                </div>
                <x-button data-modal-trigger="availability-create-modal" variant="primary" size="sm">
                    <x-icon name="plus" class="w-4 h-4" />
                    時間枠を追加
                </x-button>
            </div>
        </x-slot:header>

        <div class="overflow-x-auto">
            <div
                class="min-w-[640px] grid border border-[var(--border-subtle)] rounded-md overflow-hidden select-none"
                data-availability-calendar
                data-hour-start="{{ $hourStart }}"
                data-hour-end="{{ $hourEnd }}"
                style="grid-template-columns: 56px repeat(7, minmax(0, 1fr)); grid-template-rows: 28px repeat({{ $hoursCount }}, {{ $rowHeightPx }}px);"
            >
                {{-- ヘッダ行 (grid-row 1) --}}
                <div class="bg-surface-sunken/60 border-b border-r border-[var(--border-subtle)]"></div>
                @foreach ($dayLabels as $dow => $label)
                    @php
                        $headerColor = match ($dow) {
                            0 => 'text-danger-600',
                            6 => 'text-info-600',
                            default => 'text-ink-600',
                        };
                    @endphp
                    <div class="text-center text-[11px] font-bold uppercase tracking-wider py-1 bg-surface-sunken/60 border-b border-r last:border-r-0 border-[var(--border-subtle)] {{ $headerColor }}">
                        {{ $label }}
                    </div>
                @endforeach

                {{-- 時刻ラベル + 空セル(1 時間粒度、grid-row は header 行の次から) --}}
                @for ($i = 0; $i < $hoursCount; $i++)
                    @php
                        $hour = $hourStart + $i;
                        $hhmm = sprintf('%02d:00', $hour);
                        $gridRow = $i + 2; // header の次から
                    @endphp

                    {{-- 時刻ラベル(セルと同じ border-b でラインを完全一致させる) --}}
                    <div
                        class="text-[10px] text-ink-500 tabular-nums pr-1 text-right border-r border-b border-[var(--border-subtle)] flex items-start justify-end pt-0.5"
                        style="grid-row: {{ $gridRow }}; grid-column: 1;"
                    >
                        {{ $hhmm }}
                    </div>

                    @foreach ($dayLabels as $dow => $_)
                        <button
                            type="button"
                            class="border-r last:border-r-0 border-b border-[var(--border-subtle)] hover:bg-primary-50 transition-colors duration-fast cursor-pointer text-left"
                            data-availability-cell
                            data-day-of-week="{{ $dow }}"
                            data-hour="{{ $hour }}"
                            style="grid-row: {{ $gridRow }}; grid-column: {{ $dow + 2 }};"
                            aria-label="{{ $dayLabels[$dow] }}曜日 {{ $hhmm }} に時間枠を追加"
                        ></button>
                    @endforeach
                @endfor

                {{-- 登録済ブロックを同一グリッドに重ねて配置(grid-row span で正確な高さを得る) --}}
                @foreach ($blocksByDay as $dow => $blocks)
                    @foreach ($blocks as $block)
                        @php
                            $rowStart = $timeToGridRow($block['start'], ceil: false);
                            $rowEnd = $timeToGridRow($block['end'], ceil: true);
                            if ($rowEnd <= $rowStart) {
                                continue;
                            }
                            $primaryId = $block['ids'][0];
                            $bgClass = $block['is_active']
                                ? 'bg-primary-500/90 hover:bg-primary-600 border-primary-700 text-white'
                                : 'bg-ink-300/85 hover:bg-ink-400 border-ink-500 text-ink-900';
                            $mergedHint = count($block['ids']) > 1
                                ? '(' . count($block['ids']) . ' 枠を連結表示)'
                                : '';
                        @endphp
                        <button
                            type="button"
                            class="m-0.5 rounded-md border-l-4 px-1.5 py-1 text-[10px] font-semibold text-left shadow-sm transition-colors overflow-hidden z-10 {{ $bgClass }}"
                            style="grid-row: {{ $rowStart }} / {{ $rowEnd }}; grid-column: {{ $dow + 2 }};"
                            data-modal-trigger="availability-edit-modal-{{ $primaryId }}"
                            aria-label="{{ $dayLabels[$dow] }}曜日 {{ $block['start'] }}〜{{ $block['end'] }} の時間枠を編集 {{ $mergedHint }}"
                        >
                            <div class="tabular-nums leading-tight">{{ $block['start'] }}</div>
                            <div class="tabular-nums leading-tight opacity-90">{{ $block['end'] }}</div>
                            @if (! $block['is_active'])
                                <div class="text-[9px] opacity-80 mt-0.5">無効</div>
                            @endif
                            @if ($mergedHint !== '')
                                <div class="text-[9px] opacity-80 mt-0.5">{{ $mergedHint }}</div>
                            @endif
                        </button>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-xs text-ink-600">
            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-primary-500"></span>有効な時間枠</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-ink-300"></span>一時停止中(無効)</span>
            <span class="text-ink-500">空白セルをクリックで 1 時間枠を追加 / ドラッグで複数スロットを選択</span>
        </div>
    </x-card>
</div>

{{-- 新規作成モーダル --}}
<x-modal id="availability-create-modal" title="時間枠を追加" size="md">
    <x-slot:body>
        @include('settings.availability._partials.form', [
            'action' => route('settings.availability.store'),
            'method' => 'POST',
            'availability' => null,
            'modalId' => 'availability-create-modal',
        ])
    </x-slot:body>
</x-modal>

{{-- 編集 / 削除モーダル(各 CoachAvailability ごとに 1 つ) --}}
@foreach ($availabilities as $availability)
    <x-modal id="availability-edit-modal-{{ $availability->id }}" title="時間枠を編集" size="md">
        <x-slot:body>
            @include('settings.availability._partials.form', [
                'action' => route('settings.availability.update', $availability),
                'method' => 'PATCH',
                'availability' => $availability,
                'modalId' => 'availability-edit-modal-' . $availability->id,
            ])

            <div class="mt-4 pt-3 border-t border-[var(--border-subtle)] flex items-center justify-between gap-3 text-sm">
                <p class="text-xs text-ink-500">この時間枠を削除すると、受講生の予約画面から外れます(既存の予約は影響を受けません)。</p>
                <form
                    method="POST"
                    action="{{ route('settings.availability.destroy', $availability) }}"
                    onsubmit="return confirm('この時間枠を削除します。よろしいですか?');"
                >
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="ghost" size="sm" class="text-danger-700 hover:bg-danger-50">
                        削除する
                    </x-button>
                </form>
            </div>
        </x-slot:body>
    </x-modal>
@endforeach
