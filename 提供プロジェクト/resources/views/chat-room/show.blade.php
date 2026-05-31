{{--
    chat ルーム表示画面。受講生・コーチ・管理者(監査=閲覧のみ)が共用し、閲覧者ロールで表示と権限を出し分ける。
    構成: パンくず → 2 カラム[左: ルーム一覧ペイン(rooms-pane) / 右: ヘッダ(資格名 + 相手表示 + 監査/コーチ未割当バッジ) → メッセージ一覧領域(吹き出し item の繰り返し or 空メッセージ) → 送信フォーム(送信権限あり時) or 送信不可の注意文]
    フロント挙動: 受講生・コーチ閲覧時のみ、メッセージ一覧をリアルタイム受信して追記する素の JS を読み込む(末尾に複製元 template を出力)。管理者監査時は JS なし。吹き出し本文は自動エスケープ表示(改行保持)。
--}}
@extends('layouts.app')

@section('title', $room->enrollment->certification->name . ' の chat')

@section('content')
    @php
        $viewer = auth()->user();
        $isAdminContext = request()->routeIs('admin.*');
        $viewerIsStudent = $viewer->role === \App\Enums\UserRole::Student;
        $viewerIsAdmin = $viewer->role === \App\Enums\UserRole::Admin;

        if ($isAdminContext) {
            $backRoute = 'admin.chat-rooms.index';
            $backLabel = 'chat 監査';
        } elseif ($viewer->role === \App\Enums\UserRole::Coach) {
            $backRoute = 'coach.chat.index';
            $backLabel = 'chat';
        } else {
            $backRoute = 'chat.index';
            $backLabel = 'chat';
        }

        $coaches = $room->enrollment->certification->coaches;
        $coachUnassigned = $coaches->isEmpty();
        $certName = $room->enrollment->certification->name;
        $studentName = $room->enrollment->user->name;

        if ($viewerIsAdmin) {
            $headerSubtitle = '受講生: '.$studentName.($coaches->isNotEmpty() ? ' · 担当コーチ: '.$coaches->pluck('name')->implode(' / ') : ' · 担当コーチ未割当');
        } else {
            $headerSubtitle = $viewerIsStudent
                ? ($coachUnassigned ? '担当コーチ未割当' : '担当コーチ: '.$coaches->pluck('name')->implode(' / '))
                : '受講生: '.$studentName;
        }
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => $backLabel, 'href' => route($backRoute)],
        ['label' => $certName],
    ]" />

    <div
        class="mt-4 grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] bg-surface-raised border border-subtle rounded-2xl overflow-hidden shadow-sm h-[calc(100vh-180px)] min-h-[560px]"
    >
        @include('chat-room._partials.rooms-pane', [
            'navRooms' => $navRooms,
            'currentRoom' => $room,
            'navRoomUnreadCounts' => $navRoomUnreadCounts ?? [],
            'coachFilters' => $coachFilters ?? null,
            'adminFilters' => $adminFilters ?? null,
        ])

        <section class="flex flex-col overflow-hidden bg-surface-canvas min-w-0">
            <header class="px-6 py-3 bg-surface-raised border-b border-subtle flex items-center gap-3">
                <x-avatar :name="$certName" size="md" />
                <div class="min-w-0 flex-1">
                    <div class="font-display font-bold text-base text-ink-900 truncate">
                        {{ $certName }}
                    </div>
                    <div class="text-[11px] truncate {{ $viewerIsStudent && $coachUnassigned ? 'text-warning-700 font-semibold' : 'text-ink-500' }}">
                        {{ $headerSubtitle }}
                    </div>
                </div>
                @if ($viewerIsAdmin)
                    <x-badge variant="info">監査モード(閲覧のみ)</x-badge>
                @elseif ($coachUnassigned)
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
                    @include('chat-room._partials.empty-message', [
                        'title' => 'まだメッセージはありません',
                        'description' => $viewerIsAdmin
                            ? '受講生 / コーチが送信するとここに表示されます。'
                            : ($coachUnassigned
                                ? '担当コーチが割り当てられるとメッセージを送れるようになります。'
                                : '最初のメッセージを送ってみましょう。'),
                    ])
                @else
                    @foreach ($messages as $message)
                        @include('chat-room._partials.message-item', ['message' => $message])
                    @endforeach
                @endif
            </div>

            @can('sendMessage', $room)
                @include('chat-room._partials.message-form', ['room' => $room])
            @else
                @unless ($viewerIsAdmin)
                    <div class="border-t border-subtle px-6 py-4 text-sm text-warning-700 bg-warning-50">
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

    @unless ($viewerIsAdmin)
        @include('chat-room._partials.message-template')

        <script>
            window.chatRoomId = @json($room->id);
            window.authUserId = @json(auth()->id());
        </script>
        @vite('resources/js/chat/realtime.js')
    @endunless
@endsection
