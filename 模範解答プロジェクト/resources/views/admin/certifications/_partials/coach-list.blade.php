<x-card padding="lg" shadow="sm">
    <div class="flex items-center justify-between gap-2">
        <div>
            <h2 class="text-base font-semibold text-ink-900">担当コーチ</h2>
            <p class="text-xs text-ink-500 mt-1">{{ $certification->coaches->count() }} 名のコーチが担当</p>
        </div>
        @if ($assignableCoaches->isNotEmpty())
            <x-button variant="primary" size="sm" data-modal-trigger="assign-coach-modal">
                <x-icon name="plus" class="w-4 h-4" />
                コーチを追加
            </x-button>
        @endif
    </div>

    @if ($certification->coaches->isEmpty())
        <div class="mt-6 text-sm text-ink-500 text-center py-6">
            まだコーチが割り当てられていません。
        </div>
    @else
        <ul class="mt-4 divide-y divide-[var(--border-subtle)]">
            @foreach ($certification->coaches as $coach)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-avatar :src="$coach->avatar_url" :name="$coach->name ?? '?'" size="sm" />
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-ink-900 truncate">{{ $coach->name ?? '(未設定)' }}</div>
                            <div class="text-xs text-ink-500 font-mono truncate">{{ $coach->email }}</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.certifications.coaches.destroy', [$certification, $coach]) }}" class="shrink-0">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="ghost" size="sm">
                            <x-icon name="x-mark" class="w-4 h-4" />
                            解除
                        </x-button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
