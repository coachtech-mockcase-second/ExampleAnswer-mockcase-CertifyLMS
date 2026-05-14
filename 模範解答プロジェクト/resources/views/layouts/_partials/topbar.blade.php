@php
    $user = auth()->user();
    $notificationBadge = $sidebarBadges['notifications'] ?? 0;
    $roleAvatarBg = match (optional($user)->role?->value) {
        'admin' => 'bg-primary-600',
        'coach' => 'bg-secondary-600',
        'student' => 'bg-success-600',
        default => 'bg-ink-300',
    };
    $userInitial = $user?->name ? mb_substr($user->name, 0, 1) : '?';
    $searchPlaceholder = match (optional($user)->role?->value) {
        'admin' => 'ユーザー・資格・コーチを検索...',
        'coach' => '受講生・教材を検索...',
        'student' => '教材・問題・質問を検索...',
        default => '検索...',
    };
@endphp

<header class="sticky top-0 z-20 flex items-center gap-3 lg:gap-4 px-4 lg:px-8 py-3 border-b border-[var(--border-subtle)] bg-surface-canvas/85 backdrop-blur-md">
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

    {{-- 検索バー --}}
    <div class="relative flex-1 max-w-[320px] hidden sm:block">
        <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
        <input
            type="search"
            placeholder="{{ $searchPlaceholder }}"
            class="w-full text-[13px] py-2 pl-9 pr-3 rounded-full bg-ink-50 border border-transparent placeholder:text-ink-400 focus:outline-none focus:bg-white focus:border-primary-300 focus:ring-2 focus:ring-primary-500/15 transition-colors"
        >
    </div>

    <div class="flex-1"></div>

    {{-- 通知ベル（フレーム付き） --}}
    @if (Route::has('notifications.index'))
        <a
            href="{{ route('notifications.index') }}"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-[12px] text-ink-700 bg-white/50 border border-[var(--border-subtle)] hover:bg-white hover:border-primary-200 hover:text-primary-700 hover:shadow-md transition-all"
            aria-label="通知 ({{ $notificationBadge }} 件未読)"
        >
            <x-icon name="bell" class="w-[18px] h-[18px]" />
            @if ($notificationBadge > 0)
                <span class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 inline-flex items-center justify-center rounded-full bg-primary-600 text-white text-[10px] font-bold font-display tnum border-2 border-surface-canvas">
                    {{ $notificationBadge > 99 ? '99+' : $notificationBadge }}
                </span>
            @endif
        </a>
    @endif

    {{-- ユーザーピル (アバター + 名前 + ▼) --}}
    @if ($user)
        <x-dropdown align="right">
            <x-slot:trigger>
                <button type="button" class="flex items-center gap-2 pl-1 pr-3 py-1 rounded-full hover:bg-ink-50 transition-colors">
                    <span class="inline-flex w-7 h-7 items-center justify-center rounded-full text-white text-[11px] font-semibold {{ $roleAvatarBg }}">
                        {{ $userInitial }}
                    </span>
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
