{{--
    面談予約の詳細画面。受講生・担当コーチが共用し、閲覧者ロールと面談ステータスで表示要素を出し分ける。
    構成: パンくず → ヘッダ(ステータスバッジ + 日時 + キャンセルモーダル起動ボタン[条件]) → 左カラム[相談内容カード / 面談 URL カード or 未設定アラート / コーチのメモ入力フォーム or 完了後の閲覧メモ] → 右カラム[面談情報 dl(受講生・コーチ・資格・予約日時、キャンセル/完了時は追加項目)]
    フロント挙動: キャンセルは確認モーダル(素の JS、_modals/cancel-confirm)。それ以外は JS なし。面談 URL は別タブ遷移リンク。
--}}
@extends('layouts.app')

@section('title', '面談予約 詳細')

@php
    use App\Enums\MeetingStatus;
    use App\Enums\UserRole;

    $user = auth()->user();
    $isCoach = $user?->role === UserRole::Coach;
    $isOwner = $meeting->student_id === $user?->id || $meeting->coach_id === $user?->id;
    $canCancel = $isOwner && $meeting->status === MeetingStatus::Reserved && $meeting->scheduled_at->greaterThan(now());
    $canEditMemo = $isCoach && $meeting->coach_id === $user->id && in_array($meeting->status, [MeetingStatus::Reserved, MeetingStatus::Completed], true);
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談予約', 'href' => $isCoach ? route('coach.meetings.index') : route('meetings.index')],
        ['label' => '詳細'],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                @include('meeting._partials.status-badge', ['status' => $meeting->status])
                <span class="text-xs text-ink-500">{{ $meeting->enrollment->certification->name }}</span>
            </div>
            <h1 class="mt-2 font-display text-2xl font-bold text-ink-900 tabular-nums">
                {{ $meeting->scheduled_at->translatedFormat('Y年n月j日 (D) H:i') }}
                <span class="text-base font-medium text-ink-500 ml-1">〜 {{ $meeting->scheduled_at->copy()->addHour()->format('H:i') }}</span>
            </h1>
            <p class="text-sm text-ink-500 mt-1">60 分 / 担当コーチ {{ $meeting->coach->name }}</p>
        </div>

        @if ($canCancel)
            @include('meeting._modals.cancel-confirm', ['meeting' => $meeting])
        @endif
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_320px]">
        <div class="space-y-4 min-w-0">
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <h2 class="text-sm font-bold text-ink-900">相談内容</h2>
                </x-slot:header>
                <p class="text-sm text-ink-700 whitespace-pre-wrap">{{ $meeting->topic }}</p>
            </x-card>

            @if ($meeting->status === MeetingStatus::Reserved && $meeting->meeting_url_snapshot)
                <x-card padding="md" shadow="sm">
                    <x-slot:header>
                        <h2 class="text-sm font-bold text-ink-900">面談 URL</h2>
                    </x-slot:header>
                    <div class="flex items-center gap-3 flex-wrap">
                        <a href="{{ $meeting->meeting_url_snapshot }}" target="_blank" rel="noopener"
                           class="text-sm text-primary-700 hover:underline break-all">
                            {{ $meeting->meeting_url_snapshot }}
                        </a>
                        <x-link-button href="{{ $meeting->meeting_url_snapshot }}" variant="primary" target="_blank" rel="noopener">
                            <x-icon name="video-camera" class="w-4 h-4" />
                            面談に参加する
                        </x-link-button>
                    </div>
                    <p class="mt-2 text-[11px] text-ink-500">面談開始時刻になったら上記の URL から入室してください。</p>
                </x-card>
            @elseif ($meeting->status === MeetingStatus::Reserved && ! $meeting->meeting_url_snapshot)
                <x-alert type="warning">
                    <x-slot:title>面談 URL が未設定です</x-slot:title>
                    担当コーチが面談 URL を未設定の状態です。コーチへ連絡し、設定を依頼してください。
                </x-alert>
            @endif

            @if ($canEditMemo)
                @include('meeting.coach._memo_form', ['meeting' => $meeting])
            @elseif ($meeting->status === MeetingStatus::Completed && $meeting->meetingMemo)
                <x-card padding="md" shadow="sm">
                    <x-slot:header>
                        <h2 class="text-sm font-bold text-ink-900">コーチからの面談メモ</h2>
                    </x-slot:header>
                    <p class="text-sm text-ink-700 whitespace-pre-wrap">{{ $meeting->meetingMemo->body }}</p>
                </x-card>
            @endif
        </div>

        <aside class="space-y-3">
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <h2 class="text-sm font-bold text-ink-900">面談情報</h2>
                </x-slot:header>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-500">受講生</dt>
                        <dd class="text-ink-900 truncate">{{ $meeting->student->name }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-500">担当コーチ</dt>
                        <dd class="text-ink-900 truncate">{{ $meeting->coach->name }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-500">資格</dt>
                        <dd class="text-ink-900 truncate">{{ $meeting->enrollment->certification->name }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-500">予約日時</dt>
                        <dd class="text-ink-900 tabular-nums">{{ $meeting->created_at->format('Y-m-d H:i') }}</dd>
                    </div>
                    @if ($meeting->status === MeetingStatus::Canceled)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-500">キャンセル者</dt>
                            <dd class="text-ink-900 truncate">{{ $meeting->canceledBy?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-500">キャンセル日時</dt>
                            <dd class="text-ink-900 tabular-nums">{{ $meeting->canceled_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    @endif
                    @if ($meeting->status === MeetingStatus::Completed)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-500">完了日時</dt>
                            <dd class="text-ink-900 tabular-nums">{{ $meeting->completed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>
        </aside>
    </div>
@endsection
