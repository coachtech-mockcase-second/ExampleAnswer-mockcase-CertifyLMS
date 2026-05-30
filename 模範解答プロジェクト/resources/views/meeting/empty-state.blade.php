{{--
    面談予約の入口画面。受講中資格の有無で表示を切り替える。
    構成: パンくず → ヘッダ → [受講資格なし: 空状態カード(資格カタログ導線) / 受講資格あり: 資格切替(empty-state バリアント、選んだ資格の予約画面へ遷移)]
    JS なし(リンク遷移のみ)。
--}}
@extends('layouts.app')

@section('title', '面談予約')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談予約'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">面談予約</h1>
        <p class="text-sm text-ink-500 mt-1">担当コーチとの 60 分面談を予約できます。</p>
    </div>

    @if ($enrollments->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="calendar-days"
                    title="受講中の資格がありません"
                    description="まずは資格カタログから受講したい資格を選んで申込んでください。"
                >
                    @if (Route::has('certifications.index'))
                        <x-slot:action>
                            <x-link-button href="{{ route('certifications.index') }}" variant="primary">
                                <x-icon name="book-open" class="w-4 h-4" />
                                資格カタログへ
                            </x-link-button>
                        </x-slot:action>
                    @endif
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-enrollment-switcher variant="empty-state" :target-route="'meetings.create'" />
        </div>
    @endif
@endsection
