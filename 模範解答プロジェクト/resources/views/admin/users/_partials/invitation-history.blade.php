@php
    use App\Enums\InvitationStatus;

    $invBadge = fn (InvitationStatus $s) => match ($s) {
        InvitationStatus::Pending => 'warning',
        InvitationStatus::Accepted => 'success',
        InvitationStatus::Expired => 'gray',
        InvitationStatus::Revoked => 'danger',
    };

    $invitations = $user->invitations;
@endphp

<x-card padding="none" shadow="sm">
    <x-slot:header>
        <div class="flex items-center gap-2">
            <x-icon name="envelope" class="w-4 h-4 text-info-600" />
            <span>招待履歴</span>
            <span class="text-xs font-normal text-ink-500">{{ $invitations->count() }} 件</span>
        </div>
    </x-slot:header>

    @if ($invitations->isEmpty())
        <x-empty-state
            icon="envelope"
            title="招待履歴はありません"
            description="このユーザーへの招待発行はまだ記録されていません。"
        />
    @else
        <ul class="divide-y divide-[var(--border-subtle)]">
            @foreach ($invitations as $inv)
                <li class="px-6 py-3">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-badge :variant="$invBadge($inv->status)" size="sm">
                                {{ $inv->status->label() }}
                            </x-badge>
                            <span class="text-xs text-ink-500">
                                {{ $inv->role->label() }} として招待
                            </span>
                        </div>
                        <span class="text-xs text-ink-500 font-mono tabular-nums">
                            {{ $inv->created_at?->format('Y-m-d H:i') }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-xs text-ink-500 flex-wrap">
                        <span>
                            有効期限:
                            <span class="font-mono tabular-nums">{{ $inv->expires_at?->format('Y-m-d H:i') ?? '—' }}</span>
                        </span>
                        @if ($inv->accepted_at)
                            <span>
                                承認:
                                <span class="font-mono tabular-nums text-success-700">{{ $inv->accepted_at->format('Y-m-d H:i') }}</span>
                            </span>
                        @endif
                        @if ($inv->revoked_at)
                            <span>
                                取消:
                                <span class="font-mono tabular-nums text-danger-700">{{ $inv->revoked_at->format('Y-m-d H:i') }}</span>
                            </span>
                        @endif
                    </div>
                    @if ($inv->invitedBy)
                        <div class="mt-1 text-xs text-ink-500">
                            招待者: <span class="font-semibold text-ink-700">{{ $inv->invitedBy->name ?? $inv->invitedBy->email }}</span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
