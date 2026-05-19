@php
    /**
     * 共通フォーム部品(モーダル本体に埋め込む想定):
     * - $action: form の action URL
     * - $method: 'POST' (新規) or 'PATCH' (更新)
     * - $availability: 編集対象 CoachAvailability(新規時 null)
     * - $modalId: 紐づくモーダルの id(キャンセルボタン用)
     */
    $availability = $availability ?? null;
    $dayOptions = [
        0 => '日曜日',
        1 => '月曜日',
        2 => '火曜日',
        3 => '水曜日',
        4 => '木曜日',
        5 => '金曜日',
        6 => '土曜日',
    ];

    $defaults = [
        'day_of_week' => $availability?->day_of_week ?? 1,
        'start_time' => $availability ? \Illuminate\Support\Carbon::parse($availability->start_time)->format('H:i') : '09:00',
        'end_time' => $availability ? \Illuminate\Support\Carbon::parse($availability->end_time)->format('H:i') : '17:00',
        'is_active' => $availability?->is_active ?? true,
    ];
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($method === 'PATCH')
        @method('PATCH')
    @endif

    <x-form.select
        name="day_of_week"
        label="曜日"
        :options="$dayOptions"
        :value="old('day_of_week', (string) $defaults['day_of_week'])"
        :required="true"
    />

    <div class="grid gap-3 sm:grid-cols-2">
        <x-form.input
            name="start_time"
            label="開始時刻"
            type="time"
            :value="old('start_time', $defaults['start_time'])"
            :required="true"
        />
        <x-form.input
            name="end_time"
            label="終了時刻"
            type="time"
            :value="old('end_time', $defaults['end_time'])"
            :required="true"
        />
    </div>

    <x-form.checkbox
        name="is_active"
        label="この時間枠を有効にする(無効化すると受講生の予約画面から外れます)"
        :checked="(bool) old('is_active', $defaults['is_active'])"
    />

    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[var(--border-subtle)]">
        <x-button variant="ghost" data-modal-close="{{ $modalId }}" type="button">キャンセル</x-button>
        <x-button type="submit" variant="primary">保存する</x-button>
    </div>
</form>
