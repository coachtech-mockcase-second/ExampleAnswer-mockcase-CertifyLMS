@extends('layouts.app')

@section('title', $room->enrollment->certification->name . ' chat 監査')

@section('content')
    @php
        $coaches = $room->enrollment->certification->coaches;
        $certName = $room->enrollment->certification->name;
        $studentName = $room->enrollment->user->name;
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'chat 監査', 'href' => route('admin.chat-rooms.index')],
        ['label' => $certName],
    ]" />

    <div
        class="mt-4 grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] bg-surface-raised border border-[var(--border-subtle)] rounded-2xl overflow-hidden shadow-sm h-[calc(100vh-180px)] min-h-[560px]"
    >
        @include('chat-room._partials.rooms-pane', ['navRooms' => $navRooms, 'currentRoom' => $room, 'adminFilters' => $adminFilters ?? null])

        <section class="flex flex-col overflow-hidden bg-surface-canvas min-w-0">
            <header class="px-6 py-3 bg-surface-raised border-b border-[var(--border-subtle)] flex items-center gap-3">
                <x-avatar :name="$certName" size="md" />
                <div class="min-w-0 flex-1">
                    <div class="font-display font-bold text-base text-ink-900 truncate">
                        {{ $certName }}
                    </div>
                    <div class="text-[11px] text-ink-500 truncate">
                        受講生: {{ $studentName }}
                        @if ($coaches->isNotEmpty())
                            · 担当コーチ: {{ $coaches->pluck('name')->implode(' / ') }}
                        @else
                            · 担当コーチ未割当
                        @endif
                    </div>
                </div>
                <x-badge variant="info">監査モード(閲覧のみ)</x-badge>
            </header>

            <div
                aria-live="polite"
                role="log"
                aria-label="メッセージ一覧"
                class="flex-1 overflow-y-auto px-6 py-5 space-y-3"
            >
                @if ($messages->isEmpty())
                    @include('chat-room._partials.empty-message', [
                        'title' => 'まだメッセージはありません',
                        'description' => '受講生 / コーチが送信するとここに表示されます。',
                    ])
                @else
                    @foreach ($messages as $message)
                        @include('chat-room._partials.message-item', ['message' => $message])
                    @endforeach
                @endif
            </div>
        </section>
    </div>
@endsection
