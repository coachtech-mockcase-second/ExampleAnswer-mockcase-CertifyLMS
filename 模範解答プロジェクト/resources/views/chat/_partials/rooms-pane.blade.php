@props([
    'navRooms',
    'currentRoom',
])

@php
    $viewer = auth()->user();
    $viewerIsStudent = $viewer?->role === \App\Enums\UserRole::Student;
@endphp

<aside class="flex flex-col overflow-hidden bg-surface-raised border-b lg:border-b-0 lg:border-r border-[var(--border-subtle)]">
    <div class="px-4 py-3 border-b border-[var(--border-subtle)] flex items-center gap-2">
        <h2 class="font-display font-bold text-base text-ink-900">chat</h2>
        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-primary-100 text-primary-800 text-[10px] font-bold tabular-nums">
            {{ $navRooms->count() }}
        </span>
    </div>

    <div class="flex-1 overflow-y-auto">
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
            @endphp
            <a
                href="{{ route('chat.show', $r) }}"
                aria-current="{{ $isCurrent ? 'page' : 'false' }}"
                class="grid grid-cols-[auto_1fr] gap-3 items-start px-4 py-3 border-b border-[var(--border-subtle)] transition-colors duration-fast hover:bg-ink-50 {{ $isCurrent ? 'bg-primary-50' : '' }}"
            >
                <x-avatar :name="$certName" size="sm" />
                <div class="min-w-0">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-semibold text-ink-900 truncate">{{ $certName }}</span>
                        @if ($r->last_message_at)
                            <span class="text-[11px] text-ink-500 font-mono shrink-0">
                                {{ $r->last_message_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, short: true) }}
                            </span>
                        @endif
                    </div>
                    <div class="text-[11px] text-ink-500 truncate {{ $viewerIsStudent && $coaches->isEmpty() ? 'text-warning-700 font-semibold' : '' }}">
                        {{ $secondary }}
                    </div>
                    @if ($previewBody !== null)
                        <p class="text-xs text-ink-600 mt-1 truncate">{{ $previewBody }}</p>
                    @else
                        <p class="text-xs text-ink-400 italic mt-1">まだメッセージなし</p>
                    @endif
                </div>
            </a>
        @empty
            <div class="px-4 py-6 text-sm text-ink-500">
                参加中のルームがありません。
            </div>
        @endforelse
    </div>
</aside>
