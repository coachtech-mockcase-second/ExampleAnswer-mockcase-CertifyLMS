@extends('layouts.app')

@section('title', 'Googleカレンダー連携')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '設定'],
        ['label' => 'Googleカレンダー連携'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">Googleカレンダー連携</h1>
        <p class="text-sm text-ink-500 mt-1">
            ご自身の Google カレンダーと連携すると、面談予定をカレンダーに自動登録し、
            <strong>個人予定の入っている時間帯は受講生の予約画面から自動的に除外</strong>されます。
        </p>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_320px]">
        <x-card padding="md" shadow="sm">
            <x-slot:header>
                <h2 class="text-sm font-bold text-ink-900">連携の状態</h2>
            </x-slot:header>

            @if ($credential)
                <div class="rounded-xl bg-success-50 border border-success-200 px-4 py-3 flex items-center gap-3">
                    <x-icon name="check-circle" class="w-6 h-6 text-success-600 shrink-0" />
                    <div class="min-w-0">
                        <div class="font-display text-base font-bold text-success-900">連携済みです</div>
                        <div class="text-xs text-success-700 mt-0.5">
                            カレンダー ID: <span class="font-mono">{{ $credential->calendar_id }}</span>
                            / 連携日時: <span class="tabular-nums">{{ $credential->connected_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <h3 class="text-sm font-bold text-ink-900">連携を解除する</h3>
                    <p class="mt-1 text-xs text-ink-500">
                        解除すると、以降の面談予約は Google カレンダーに自動登録されなくなります。
                        既存予約は LMS 内には残ります(Google 側の event は削除されません)。
                    </p>
                    <form method="POST" action="{{ route('settings.google-calendar.destroy') }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger">
                            <x-icon name="x-mark" class="w-4 h-4" />
                            連携を解除する
                        </x-button>
                    </form>
                </div>
            @else
                <div class="rounded-xl bg-ink-50 border border-[var(--border-subtle)] px-4 py-3 flex items-center gap-3">
                    <x-icon name="exclamation-circle" class="w-6 h-6 text-ink-500 shrink-0" />
                    <div class="min-w-0">
                        <div class="font-display text-base font-bold text-ink-900">まだ連携していません</div>
                        <div class="text-xs text-ink-600 mt-0.5">
                            連携すると個人カレンダーの予定が空き枠から除外され、面談予約とイベント登録が自動化されます。
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <x-link-button href="{{ route('settings.google-calendar.redirect') }}" variant="primary">
                        <x-icon name="calendar-days" class="w-4 h-4" />
                        Googleカレンダーと連携する
                    </x-link-button>
                </div>
            @endif
        </x-card>

        <aside>
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <h2 class="text-sm font-bold text-ink-900">連携でできること</h2>
                </x-slot:header>
                <ul class="text-sm text-ink-700 space-y-2 list-disc list-inside">
                    <li>個人カレンダーの予定とぶつかる時刻は受講生に表示されなくなります</li>
                    <li>受講生が予約した瞬間に、ご自身のカレンダーに自動で面談予定が入ります</li>
                    <li>予定の説明欄にはプロフィールに登録した固定面談 URL が転記されます</li>
                    <li>キャンセル時はカレンダー側の予定も自動で削除されます</li>
                </ul>
                <div class="mt-4 text-[11px] text-ink-500 leading-relaxed">
                    Google Meet URL の自動生成は行いません。<a href="{{ route('settings.profile.edit') }}" class="text-primary-700 underline">プロフィール画面</a>で登録済の固定面談 URL がそのまま使用されます。
                </div>
            </x-card>
        </aside>
    </div>
@endsection
