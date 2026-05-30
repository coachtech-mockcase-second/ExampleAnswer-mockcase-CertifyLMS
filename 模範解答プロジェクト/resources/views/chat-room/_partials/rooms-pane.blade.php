{{--
    chat 画面左側のルーム一覧ペイン partial。閲覧者ロールで絞り込み UI と表示文言を出し分ける。
    props: navRooms(表示ルーム一覧) / currentRoom(選択中) / coachFilters・adminFilters(各ロールの絞り込み状態) / navRoomUnreadCounts(ルーム別未読数)。
    構成: ヘッダ(件数) → 絞り込みフォーム(コーチ: 未読/全件タブ + キーワード / 管理者: キーワードのみ) → ルームリンク一覧(アバター + 資格名 + 相手表示 + 未読バッジ + 最終メッセージ時刻 + 本文プレビュー)
    フロント挙動: 絞り込みは GET フォーム送信(JS なし)。各ルームはリンク遷移し、選択中は aria-current="page"。
--}}
@props([
    'navRooms',
    'currentRoom',
    'coachFilters' => null,
    'adminFilters' => null,
    'navRoomUnreadCounts' => [],
])

@php
    $viewer = auth()->user();
    $viewerIsStudent = $viewer?->role === \App\Enums\UserRole::Student;
    $coachFilter = $coachFilters['filter'] ?? 'all';
    $coachKeyword = $coachFilters['keyword'] ?? '';
    $adminKeyword = $adminFilters['keyword'] ?? '';
@endphp

<aside class="flex flex-col overflow-hidden bg-surface-raised border-b lg:border-b-0 lg:border-r border-subtle">
    <div class="px-4 py-3 border-b border-subtle flex items-center gap-2">
        <h2 class="font-display font-bold text-base text-ink-900">chat</h2>
        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-primary-100 text-primary-800 text-[10px] font-bold tabular-nums">
            {{ $navRooms->count() }}
        </span>
    </div>

    @if ($coachFilters !== null)
        {{-- コーチ閲覧時の絞り込み: タブで未読/全件、キーワード検索。current room は変えずに query string を更新する --}}
        <form method="GET" action="{{ route('chat.show', $currentRoom) }}" class="px-3 pt-3 pb-3 space-y-2 border-b border-subtle">
            <div class="flex gap-1">
                <button type="submit" name="filter" value="unread"
                    class="flex-1 px-2 py-1.5 rounded-md text-xs font-semibold transition {{ $coachFilter === 'unread' ? 'bg-primary-600 text-white' : 'bg-ink-100 text-ink-700 hover:bg-ink-200' }}">
                    未読あり
                </button>
                <button type="submit" name="filter" value="all"
                    class="flex-1 px-2 py-1.5 rounded-md text-xs font-semibold transition {{ $coachFilter === 'all' ? 'bg-primary-600 text-white' : 'bg-ink-100 text-ink-700 hover:bg-ink-200' }}">
                    すべて
                </button>
            </div>
            <input type="search" name="keyword" placeholder="受講生名 / メール"
                value="{{ $coachKeyword }}"
                class="w-full px-2.5 py-1.5 rounded-md border border-subtle bg-white text-xs focus:outline-none focus:ring-2 focus:ring-primary-500">
        </form>
    @elseif ($adminFilters !== null)
        {{-- 管理者監査時の絞り込み: キーワード検索のみ(全ルームが対象なので未読の概念は無い) --}}
        <form method="GET" action="{{ route('admin.chat-rooms.show', $currentRoom) }}" class="px-3 pt-3 pb-3 border-b border-subtle">
            <input type="search" name="keyword" placeholder="受講生名 / メール"
                value="{{ $adminKeyword }}"
                class="w-full px-2.5 py-1.5 rounded-md border border-subtle bg-white text-xs focus:outline-none focus:ring-2 focus:ring-primary-500">
        </form>
    @endif

    <div class="flex-1 overflow-y-auto">
        @php
            $isAdmin = $viewer?->role === \App\Enums\UserRole::Admin;
            $linkRouteName = $isAdmin ? 'admin.chat-rooms.show' : 'chat.show';
            $linkQuery = $coachFilters !== null
                ? array_filter(['filter' => $coachFilter, 'keyword' => $coachKeyword], fn ($v) => $v !== null && $v !== '')
                : ($adminFilters !== null
                    ? array_filter(['keyword' => $adminKeyword], fn ($v) => $v !== null && $v !== '')
                    : []);
        @endphp
        @forelse ($navRooms as $r)
            @php
                $isCurrent = $r->id === $currentRoom->id;
                $certName = $r->enrollment->certification->name;
                $coaches = $r->enrollment->certification->coaches;
                $secondary = $viewerIsStudent
                    ? ($coaches->isEmpty() ? '担当コーチ未割当' : '担当コーチ: ' . $coaches->pluck('name')->implode(' / '))
                    : '受講生: ' . $r->enrollment->user->name;
                $latest = $r->latestMessage;
                $previewBody = $latest?->body
                    ? \Illuminate\Support\Str::limit($latest->body, 38)
                    : null;
                $unreadCount = $navRoomUnreadCounts[$r->id] ?? 0;
            @endphp
            <a
                href="{{ route($linkRouteName, array_merge(['room' => $r], $linkQuery)) }}"
                aria-current="{{ $isCurrent ? 'page' : 'false' }}"
                class="grid grid-cols-[auto_1fr] gap-3 items-start px-4 py-3 border-b border-subtle transition-colors duration-fast hover:bg-ink-50 {{ $isCurrent ? 'bg-primary-50' : '' }}"
            >
                <x-avatar :name="$certName" size="sm" />
                <div class="min-w-0">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-semibold text-ink-900 truncate">{{ $certName }}</span>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if ($unreadCount > 0)
                                <x-badge variant="danger" size="sm" aria-label="未読 {{ $unreadCount }} 件">
                                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                                </x-badge>
                            @endif
                            @if ($r->last_message_at)
                                <span class="text-[11px] text-ink-500 font-mono">
                                    {{ $r->last_message_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="text-[11px] text-ink-500 truncate {{ $viewerIsStudent && $coaches->isEmpty() ? 'text-warning-700 font-semibold' : '' }}">
                        {{ $secondary }}
                    </div>
                    @if ($previewBody !== null)
                        <p class="text-xs text-ink-600 mt-1 truncate {{ $unreadCount > 0 ? 'font-semibold text-ink-900' : '' }}">{{ $previewBody }}</p>
                    @else
                        <p class="text-xs text-ink-400 italic mt-1">まだメッセージなし</p>
                    @endif
                </div>
            </a>
        @empty
            <div class="px-4 py-6 text-sm text-ink-500">
                該当するルームがありません。
            </div>
        @endforelse
    </div>
</aside>
