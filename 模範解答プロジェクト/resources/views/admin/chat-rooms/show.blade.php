@extends('layouts.app')

@section('title', $room->enrollment->certification->name . ' chat 監査')

@section('content')
    @php
        $coaches = $room->enrollment->certification->coaches;
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'chat 監査', 'href' => route('admin.chat-rooms.index')],
        ['label' => $room->enrollment->certification->name],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">{{ $room->enrollment->certification->name }} の chat</h1>
            <p class="text-sm text-ink-500 mt-1">
                {{ $room->enrollment->user->name }} さん
                @if ($coaches->isNotEmpty())
                    · 担当コーチ: {{ $coaches->pluck('name')->implode(' / ') }}
                @endif
            </p>
        </div>
        <x-badge variant="info">監査モード(閲覧のみ)</x-badge>
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
        <x-card padding="none">
            <div
                aria-live="polite"
                role="log"
                aria-label="メッセージ一覧"
                class="px-6 py-5 max-h-[60vh] overflow-y-auto space-y-3 bg-surface-canvas"
            >
                @if ($messages->isEmpty())
                    @include('chat._partials.empty-message', [
                        'title' => 'まだメッセージはありません',
                        'description' => '受講生 / コーチが送信するとここに表示されます。',
                    ])
                @else
                    @foreach ($messages as $message)
                        @include('chat._partials.message-item', ['message' => $message])
                    @endforeach
                @endif
            </div>
        </x-card>

        <aside>
            @include('chat._partials.member-list', ['room' => $room, 'coaches' => $coaches])
        </aside>
    </div>
@endsection
