<a href="{{ route('certifications.show', $certification) }}" class="group">
    <x-card padding="lg" shadow="sm" class="h-full transition-shadow group-hover:shadow-md group-hover:border-primary-200">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-base font-semibold text-ink-900 group-hover:text-primary-700 transition-colors truncate">{{ $certification->name }}</div>
                @if ($certification->category?->name)
                    <div class="text-xs text-ink-500 mt-1">{{ $certification->category->name }}</div>
                @endif
            </div>
            @if ($isEnrolled)
                <x-badge variant="success" size="sm">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                    受講中
                </x-badge>
            @endif
        </div>

        <div class="mt-3 flex items-center gap-2 flex-wrap">
            <x-badge variant="gray" size="sm">{{ $certification->difficulty->label() }}</x-badge>
        </div>

        @if ($certification->description)
            <p class="mt-4 text-sm text-ink-700 leading-relaxed line-clamp-3 border-t border-[var(--border-subtle)] pt-4">
                {{ $certification->description }}
            </p>
        @endif
    </x-card>
</a>
