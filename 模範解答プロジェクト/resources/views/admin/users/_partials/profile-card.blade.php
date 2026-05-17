@php
    use App\Enums\UserRole;
    use App\Enums\UserStatus;

    $roleBadge = match ($user->role) {
        UserRole::Admin => 'primary',
        UserRole::Coach => 'info',
        UserRole::Student => 'gray',
    };

    $statusBadge = match ($user->status) {
        UserStatus::InProgress => 'success',
        UserStatus::Invited => 'warning',
        UserStatus::Graduated => 'info',
        UserStatus::Withdrawn => 'gray',
    };
@endphp

<div class="mt-4 bg-surface-raised border border-[var(--border-subtle)] rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-6 sm:px-8 sm:py-8 flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
        {{-- 左: プロフィール --}}
        <div class="flex items-start gap-4 min-w-0 flex-1">
            <x-avatar :src="$user->avatar_url" :name="$user->name ?? '?'" size="xl" />

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-bold text-ink-900 truncate">
                        {{ $user->name ?? '(未設定)' }}
                    </h1>
                    @if ($isSelf)
                        <x-badge variant="info" size="sm">あなた</x-badge>
                    @endif
                </div>

                <p class="mt-1 text-sm text-ink-500 font-mono truncate">{{ $user->email }}</p>

                <div class="mt-3 flex items-center gap-2 flex-wrap">
                    <x-badge :variant="$roleBadge" size="md">{{ $user->role->label() }}</x-badge>
                    <x-badge :variant="$statusBadge" size="md">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-current mr-1"></span>
                        {{ $user->status->label() }}
                    </x-badge>
                </div>

                @if ($user->bio)
                    <p class="mt-4 text-sm text-ink-700 leading-relaxed whitespace-pre-wrap">{{ $user->bio }}</p>
                @endif

                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-ink-500 sm:max-w-md">
                    <dt class="font-semibold text-ink-700">登録日</dt>
                    <dd class="font-mono tabular-nums">{{ $user->created_at?->format('Y-m-d H:i') }}</dd>

                    <dt class="font-semibold text-ink-700">最終ログイン</dt>
                    <dd class="font-mono tabular-nums">
                        {{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}
                    </dd>

                    @if ($isWithdrawn)
                        <dt class="font-semibold text-ink-700">退会日</dt>
                        <dd class="font-mono tabular-nums">{{ $user->deleted_at?->format('Y-m-d H:i') }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- 右: 操作ボタン群 --}}
        @unless ($isWithdrawn)
            <div class="flex flex-col gap-2 sm:items-end sm:min-w-[180px]">
                <x-button data-modal-trigger="edit-profile-modal" variant="primary">
                    <x-icon name="pencil" class="w-4 h-4" />
                    プロフィール編集
                </x-button>

                @unless ($isSelf)
                    <x-button data-modal-trigger="change-role-modal" variant="outline">
                        <x-icon name="user-circle" class="w-4 h-4" />
                        ロール変更
                    </x-button>
                @endunless

                @if ($isInvited && $pendingInvitation)
                    <form method="POST" action="{{ route('admin.invitations.resend', $user) }}">
                        @csrf
                        <x-button type="submit" variant="outline">
                            <x-icon name="paper-airplane" class="w-4 h-4" />
                            招待を再送信
                        </x-button>
                    </form>

                    <x-button data-modal-trigger="cancel-invitation-modal" variant="danger">
                        <x-icon name="trash" class="w-4 h-4" />
                        招待を取消
                    </x-button>
                @endif

                @if ($isActive && ! $isSelf)
                    <x-button data-modal-trigger="withdraw-confirm-modal" variant="danger">
                        <x-icon name="user-minus" class="w-4 h-4" />
                        退会処理
                    </x-button>
                @endif
            </div>
        @endunless
    </div>
</div>
