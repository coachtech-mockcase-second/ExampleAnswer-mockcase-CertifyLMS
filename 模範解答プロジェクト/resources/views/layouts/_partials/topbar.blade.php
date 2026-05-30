{{--
    認証後ヘッダ（全画面共通）。
    左: モバイル用ハンバーガー + 教材検索バー（受講生のみ表示）。
    右: 通知ベル（未読バッジ + 通知ポップオーバー partial を内包）+ ユーザーピル（ドロップダウン）。
--}}
@php
    $user = auth()->user();
    $notificationBadge = $notificationBadge ?? 0;
    // 教材検索は受講生のみが対象（管理者・コーチ向けの横断検索エンドポイントは存在しない）。
    // 検索結果はデフォルト資格にスコープされるため、デフォルト資格が設定されているときだけ検索バーを出す。
    $searchCertificationId = $user?->role === \App\Enums\UserRole::Student
        ? $user->defaultEnrollment?->certification_id
        : null;
@endphp

<header class="sticky top-0 z-20 flex items-center gap-3 lg:gap-4 px-4 lg:px-8 py-3 border-b border-subtle bg-surface-canvas/85 backdrop-blur-md">
    {{-- モバイル: ハンバーガー --}}
    <button
        type="button"
        data-sidebar-toggle
        aria-label="メニューを開く"
        aria-expanded="false"
        class="lg:hidden inline-flex h-9 w-9 items-center justify-center rounded-[10px] text-ink-700 hover:bg-ink-50 transition-colors"
    >
        <x-icon name="bars-3" class="w-5 h-5" />
    </button>

    {{-- 教材検索（受講生 + デフォルト資格設定時のみ。Enter で登録資格内 Section を全文検索） --}}
    @if ($searchCertificationId !== null && Route::has('contents.search'))
        <form method="GET" action="{{ route('contents.search') }}" role="search" class="relative flex-1 max-w-[320px] hidden sm:block">
            <input type="hidden" name="certification_id" value="{{ $searchCertificationId }}">
            <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
            <input
                type="search"
                name="keyword"
                maxlength="200"
                placeholder="教材を検索..."
                aria-label="教材を検索"
                class="w-full text-[13px] py-2 pl-9 pr-3 rounded-full bg-ink-50 border border-transparent placeholder:text-ink-400 focus:outline-none focus:bg-white focus:border-primary-300 focus:ring-2 focus:ring-primary-500/15 transition-colors"
            >
        </form>
    @endif

    <div class="flex-1"></div>

    {{-- 通知ベル + 通知ポップオーバー(ベル横アンカー) --}}
    @if (Route::has('notifications.index'))
        <div class="relative" data-notification-popover-root>
            <button
                type="button"
                data-notification-popover-trigger
                aria-haspopup="dialog"
                aria-expanded="false"
                aria-controls="notification-popover-panel"
                aria-label="通知 ({{ $notificationBadge }} 件未読)"
                class="relative inline-flex h-9 w-9 items-center justify-center rounded-[12px] text-ink-700 bg-white/50 border border-subtle hover:bg-white hover:border-primary-200 hover:text-primary-700 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 transition-all"
            >
                <x-icon name="bell" class="w-[18px] h-[18px]" />
                <span
                    data-notification-popover-badge
                    @class([
                        'absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 inline-flex items-center justify-center rounded-full bg-primary-600 text-white text-[10px] font-bold font-display tnum border-2 border-surface-canvas',
                        'hidden' => $notificationBadge <= 0,
                    ])
                >
                    {{ $notificationBadge > 99 ? '99+' : $notificationBadge }}
                </span>
            </button>

            @include('notifications._partials.notification-popover')
        </div>
    @endif

    {{-- ユーザーピル (アバター + 名前 + ▼) --}}
    @if ($user)
        <x-dropdown align="right">
            <x-slot:trigger>
                <button type="button" class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full hover:bg-ink-50 transition-colors">
                    <x-avatar :src="$user->avatar_url" :name="$user->name" size="sm" class="w-7 h-7" />
                    <span class="hidden md:inline text-xs font-semibold text-ink-900">{{ $user->name }}</span>
                    <x-icon name="chevron-down" class="hidden md:block w-3 h-3 text-ink-500" />
                </button>
            </x-slot:trigger>

            @if (Route::has('settings.profile.edit'))
                <x-dropdown.item :href="route('settings.profile.edit')" icon="user-circle">プロフィール</x-dropdown.item>
            @endif
            @if (Route::has('logout'))
                <x-dropdown.item :href="route('logout')" method="post" icon="arrow-right-on-rectangle">ログアウト</x-dropdown.item>
            @endif
        </x-dropdown>
    @endif
</header>
