{{--
    面談予約フォーム画面。カレンダーで日付を選び、空き時刻スロットを選択して 60 分面談を予約する。
    構成: パンくず → 予約/履歴タブ(+残り面談回数・追加購入) → 見出し → 資格切替(inline) → 自動コーチ割当の案内カード → 残数不足アラート(条件) → 予約フォーム[左: 月送りカレンダー + 時刻スロット / 右: 選択内容サマリ + 相談内容 textarea + 送信ボタン]
    フロント挙動: 素の JS でカレンダー日付セルと時刻スロットを描画し、日付選択→その日の空きスロット表示→スロット選択で右サマリ更新 + 送信ボタン活性化(hidden input に選択日時を反映)。月の前後送り / 今日ボタンあり。
--}}
@extends('layouts.app')

@section('title', '面談を予約する')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談'],
    ]" />

    @include('meeting._partials.nav-tabs', ['meetingsRemaining' => $meetingsRemaining])

    <div class="mt-4">
        <div class="text-xs font-semibold tracking-wider text-primary-700 uppercase">面談予約</div>
        <h1 class="mt-1 text-2xl font-bold text-ink-900">担当コーチと面談を予約する</h1>
        <p class="mt-1 text-sm text-ink-500">
            空き枠から日時を選んで予約してください。コーチは自動で割り当てられます。
        </p>
    </div>

    {{-- inline Switcher (v3.5、予約画面のみ埋込) --}}
    <div class="mt-4">
        <x-enrollment-switcher variant="inline" :current="$enrollment" :target-route="'meetings.create'" />
    </div>

    {{-- 自動コーチ割当の案内カード (design ref のコーチカードを「自動割当案内」に置換) --}}
    <div class="mt-6 rounded-2xl px-6 py-5 bg-gradient-tropic-soft text-ink-900 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-surface-raised text-primary-700 shrink-0">
                <x-icon name="user-group" class="w-6 h-6" />
            </div>
            <div class="min-w-0">
                <div class="font-display text-lg font-bold">{{ $enrollment->certification->name }} の担当コーチが自動で割り当てられます</div>
                <p class="mt-1 text-sm text-ink-700">
                    時刻を選ぶと、対応可能なコーチの中から最も担当余裕があるコーチが選ばれます。1 回 60 分固定です。
                </p>
                <p class="mt-1 text-xs text-ink-700">
                    コーチが Google カレンダー連携を有効にしている場合、個人予定とぶつかる時刻は自動的に除外されます。
                </p>
            </div>
        </div>
    </div>

    @if ($meetingsRemaining < 1)
        <div class="mt-6">
            <x-alert type="warning">
                <x-slot:title>残面談回数が不足しています</x-slot:title>
                <div class="mt-1 text-sm">面談を予約するには、追加面談を購入してください。</div>
                @if (Route::has('meeting-quota.checkout.select'))
                    <div class="mt-3">
                        <x-link-button href="{{ route('meeting-quota.checkout.select') }}" variant="primary">追加面談を購入する</x-link-button>
                    </div>
                @endif
            </x-alert>
        </div>
    @endif

    <form novalidate method="POST" action="{{ route('meetings.store', $enrollment) }}" id="meeting-store-form"
          class="mt-6 grid gap-5 lg:grid-cols-[1fr_320px]">
        @csrf

        {{-- LEFT: Calendar + Slot picker --}}
        <div class="space-y-4 min-w-0">
            <div class="rounded-2xl bg-surface-raised border border-subtle p-5 shadow-sm"
                 data-meeting-calendar
                 data-availability-endpoint="{{ route('meetings.availability', $enrollment) }}">
                <div class="flex items-center gap-2">
                    <button type="button" data-cal-prev class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-subtle text-ink-700 hover:bg-ink-50">
                        <x-icon name="chevron-left" class="w-4 h-4" />
                    </button>
                    <h2 class="font-display text-base font-bold tabular-nums" data-cal-title>—</h2>
                    <button type="button" data-cal-next class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-subtle text-ink-700 hover:bg-ink-50">
                        <x-icon name="chevron-right" class="w-4 h-4" />
                    </button>
                    <div class="flex-1"></div>
                    <button type="button" data-cal-today class="text-xs px-3 py-1 rounded-md border border-subtle text-ink-700 hover:bg-ink-50">今日</button>
                </div>

                <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase tracking-wider">
                    <div class="text-danger-600 py-1">日</div>
                    <div class="text-ink-500 py-1">月</div>
                    <div class="text-ink-500 py-1">火</div>
                    <div class="text-ink-500 py-1">水</div>
                    <div class="text-ink-500 py-1">木</div>
                    <div class="text-ink-500 py-1">金</div>
                    <div class="text-info-600 py-1">土</div>
                </div>

                <div class="mt-1 grid grid-cols-7 gap-1" data-cal-grid>
                    {{-- 日付セルは JS で描画 --}}
                </div>

                <div class="mt-4 flex flex-wrap gap-4 text-xs text-ink-600">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-primary-50 border border-primary-200"></span>選択可能</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-primary-600"></span>選択中</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm bg-warning-500"></span>今日</span>
                </div>
            </div>

            <div class="rounded-2xl bg-surface-raised border border-subtle p-5 shadow-sm">
                <h3 class="font-display text-base font-bold text-ink-900" data-slots-title>日付を選択してください</h3>
                <p class="text-xs text-ink-500 mt-1">所要時間: 60 分(固定) / 各枠は先着順 / コーチは自動割当</p>

                <div class="mt-4 grid grid-cols-2 gap-2" data-slots-grid>
                    {{-- 時刻スロットは JS で描画 --}}
                </div>

                <div class="mt-3 hidden text-sm text-ink-500" data-slots-empty>
                    <x-icon name="information-circle" class="w-4 h-4 inline" />
                    この日に空きスロットはありません。別の日をお選びください。
                </div>
            </div>
        </div>

        {{-- RIGHT: Booking form --}}
        <aside>
            <div class="sticky top-20 rounded-2xl bg-surface-raised border border-subtle p-5 shadow-sm">
                <h3 class="flex items-center gap-1.5 text-sm font-bold text-ink-900">
                    <x-icon name="check-badge" class="w-4 h-4 text-primary-600" />
                    予約内容
                </h3>

                <div class="mt-3 rounded-xl bg-primary-50 px-4 py-3 flex items-center gap-2.5"
                     data-selection-card>
                    <x-icon name="calendar-days" class="w-5 h-5 text-primary-700 shrink-0" />
                    <div class="min-w-0">
                        <div class="font-display text-sm font-bold text-primary-900 leading-tight" data-selection-label>
                            日時を選択してください
                        </div>
                        <div class="text-[11px] text-primary-700 mt-0.5">担当コーチは自動で割り当てられます</div>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-ink-800">面談で相談したいこと</label>
                    <textarea name="topic" rows="5" required maxlength="1000"
                              class="mt-1.5 w-full rounded-md border border-default bg-surface-raised px-3 py-2 text-sm text-ink-900 focus:border-primary-400 focus:ring-2 focus:ring-primary-200 focus:outline-none"
                              placeholder="例: アルゴリズム分野の模試正答率が伸び悩んでいます。学習計画の見直しを相談したいです。">{{ old('topic') }}</textarea>
                    <x-form.error name="topic" />
                    <p class="mt-1 text-[11px] text-ink-500">事前に伝えておくとコーチが準備して臨めます。</p>
                </div>

                <input type="hidden" name="scheduled_at" data-scheduled-input value="" />

                <div class="mt-4">
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-md font-semibold bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            data-submit-button
                            disabled>
                        <x-icon name="paper-airplane" class="w-4 h-4" />
                        この日時で予約する
                    </button>
                    <p class="mt-2 text-[11px] text-ink-500 text-center">予約完了後、コーチに通知メールが届きます。</p>
                </div>
            </div>
        </aside>
    </form>
@endsection

@push('scripts')
    @vite('resources/js/mentoring/slot-picker.js')
@endpush
