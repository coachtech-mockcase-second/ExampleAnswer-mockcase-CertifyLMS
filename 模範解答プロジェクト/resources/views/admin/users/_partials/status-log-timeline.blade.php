@php
    use App\Enums\UserStatus;

    $statusBadge = fn (UserStatus $s) => match ($s) {
        UserStatus::Active => 'success',
        UserStatus::Invited => 'warning',
        UserStatus::Withdrawn => 'gray',
    };

    $logs = $user->statusLogs;
@endphp

<x-card padding="none" shadow="sm">
    <x-slot:header>
        <div class="flex items-center gap-2">
            <x-icon name="clock" class="w-4 h-4 text-secondary-600" />
            <span>ステータス変更履歴</span>
            <span class="text-xs font-normal text-ink-500">{{ $logs->count() }} 件</span>
        </div>
    </x-slot:header>

    @if ($logs->isEmpty())
        <x-empty-state
            icon="clock"
            title="変更履歴はありません"
            description="このユーザーのステータス変更はまだ記録されていません。"
        />
    @else
        <ol class="relative px-6 py-5 space-y-5">
            <span class="absolute left-[34px] top-6 bottom-6 w-px bg-[var(--border-subtle)]" aria-hidden="true"></span>

            @foreach ($logs as $log)
                @php
                    $actorName = $log->changedBy?->name ?? 'システム';
                    $isSystem = $log->changedBy === null;
                @endphp
                <li class="relative flex gap-4">
                    <div class="relative z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-raised ring-2 ring-[var(--border-subtle)]">
                        <span class="h-2 w-2 rounded-full {{ $isSystem ? 'bg-ink-400' : 'bg-primary-500' }}"></span>
                    </div>
                    <div class="min-w-0 flex-1 -mt-0.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <x-badge :variant="$statusBadge($log->status)" size="sm">
                                {{ $log->status->label() }}
                            </x-badge>
                            <span class="text-xs text-ink-500 font-mono tabular-nums">
                                {{ $log->changed_at?->format('Y-m-d H:i') }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-ink-700">
                            <span class="font-semibold {{ $isSystem ? 'text-ink-500' : 'text-ink-900' }}">
                                @if ($isSystem)
                                    <x-icon name="cpu-chip" class="inline w-3.5 h-3.5 -mt-0.5" />
                                @endif
                                {{ $actorName }}
                            </span>
                            による変更
                        </p>
                        @if ($log->changed_reason)
                            <p class="mt-1 text-xs text-ink-500 leading-relaxed">
                                理由: {{ $log->changed_reason }}
                            </p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
