@extends('layouts.app')

@section('title', $room->enrollment->certification->name . ' の chat')

@section('content')
    @php
        $viewer = auth()->user();
        $viewerIsStudent = $viewer->role === \App\Enums\UserRole::Student;
        $backRoute = $viewer->role === \App\Enums\UserRole::Coach ? 'coach.chat.index' : 'chat.index';
        $coaches = $room->enrollment->certification->coaches;
        $coachUnassigned = $coaches->isEmpty();
        $certName = $room->enrollment->certification->name;
        $studentName = $room->enrollment->user->name;
        $headerSubtitle = $viewerIsStudent
            ? ($coachUnassigned ? '担当コーチ未割当' : '担当コーチ: ' . $coaches->pluck('name')->implode(' / '))
            : '受講生: ' . $studentName;
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'chat', 'href' => route($backRoute)],
        ['label' => $certName],
    ]" />

    <div
        class="mt-4 grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] bg-surface-raised border border-[var(--border-subtle)] rounded-2xl overflow-hidden shadow-sm h-[calc(100vh-180px)] min-h-[560px]"
    >
        @include('chat._partials.rooms-pane', ['navRooms' => $navRooms, 'currentRoom' => $room, 'coachFilters' => $coachFilters ?? null])

        <section class="flex flex-col overflow-hidden bg-surface-canvas min-w-0">
            <header class="px-6 py-3 bg-surface-raised border-b border-[var(--border-subtle)] flex items-center gap-3">
                <x-avatar :name="$certName" size="md" />
                <div class="min-w-0 flex-1">
                    <div class="font-display font-bold text-base text-ink-900 truncate">
                        {{ $certName }}
                    </div>
                    <div class="text-[11px] truncate {{ $viewerIsStudent && $coachUnassigned ? 'text-warning-700 font-semibold' : 'text-ink-500' }}">
                        {{ $headerSubtitle }}
                    </div>
                </div>
                @if ($coachUnassigned)
                    <x-badge variant="warning">コーチ未割当</x-badge>
                @endif
            </header>

            <div
                id="chat-messages-list"
                aria-live="polite"
                role="log"
                aria-label="メッセージ一覧"
                class="flex-1 overflow-y-auto px-6 py-5 space-y-3"
            >
                @if ($messages->isEmpty())
                    @include('chat._partials.empty-message', [
                        'title' => 'まだメッセージはありません',
                        'description' => $coachUnassigned
                            ? '担当コーチが割り当てられるとメッセージを送れるようになります。'
                            : '最初のメッセージを送ってみましょう。',
                    ])
                @else
                    @foreach ($messages as $message)
                        @include('chat._partials.message-item', ['message' => $message])
                    @endforeach
                @endif
            </div>

            @can('sendMessage', $room)
                @include('chat._partials.message-form', ['room' => $room])
            @else
                @unless (auth()->user()->role === \App\Enums\UserRole::Admin)
                    <div class="border-t border-[var(--border-subtle)] px-6 py-4 text-sm text-warning-700 bg-warning-50">
                        @if ($coachUnassigned)
                            担当コーチが割り当てられていないため、メッセージを送信できません。
                        @else
                            このルームでメッセージを送信する権限がありません。
                        @endif
                    </div>
                @endunless
            @endcan
        </section>
    </div>

    @include('chat._partials.message-template')

    <script>
        window.chatRoomId = @json($room->id);
        window.authUserId = @json(auth()->id());
    </script>
    @vite('resources/js/chat/realtime.js')
@endsection
