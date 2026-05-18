@php
    /** @var \Illuminate\Database\Eloquent\Collection $parts */
    /** @var array<int, string> $completedSectionIds */
@endphp

@if ($parts->isEmpty())
    <x-empty-state
        icon="book-open"
        title="教材がまだ公開されていません"
        description="この資格にはまだ公開済 Part がありません。担当コーチが公開すると表示されます。" />
@else
    <div class="space-y-6">
        @foreach ($parts as $part)
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <div class="flex items-baseline justify-between gap-3">
                        <a href="{{ route('learning.parts.show', $part) }}" class="text-base font-bold text-ink-900 hover:text-primary-700 transition-colors">
                            Part {{ $loop->iteration }} ・ {{ $part->title }}
                        </a>
                        <span class="text-xs text-ink-500 tabular-nums">{{ $part->chapters->count() }} Chapter</span>
                    </div>
                </x-slot:header>

                @if ($part->description)
                    <p class="text-sm text-ink-600 mb-4">{{ $part->description }}</p>
                @endif

                <div class="space-y-4">
                    @foreach ($part->chapters as $chapter)
                        <div class="rounded-lg border border-[var(--border-subtle)] bg-surface-canvas overflow-hidden">
                            <a href="{{ route('learning.chapters.show', $chapter) }}"
                                class="group/chapter flex items-baseline justify-between gap-3 px-4 py-3 bg-surface-sunken/40 hover:bg-primary-50 hover:text-primary-800 transition-colors">
                                <span class="text-sm font-semibold text-ink-900 group-hover/chapter:text-primary-800">Chapter {{ $loop->iteration }} ・ {{ $chapter->title }}</span>
                                <span class="flex items-center gap-1 text-xs text-ink-500 tabular-nums whitespace-nowrap group-hover/chapter:text-primary-700">
                                    {{ $chapter->sections->count() }} Section
                                    <x-icon name="chevron-right" class="w-3.5 h-3.5 transition-transform group-hover/chapter:translate-x-0.5" />
                                </span>
                            </a>

                            @if ($chapter->sections->isNotEmpty())
                                <ul class="border-t border-[var(--border-subtle)] divide-y divide-[var(--border-subtle)] bg-white">
                                    @foreach ($chapter->sections as $section)
                                        @php $isCompleted = in_array($section->id, $completedSectionIds, true); @endphp
                                        <li>
                                            <a href="{{ route('learning.sections.show', $section) }}"
                                                class="group/section flex items-center gap-2.5 px-4 py-2 text-sm hover:bg-primary-50 transition-colors {{ $isCompleted ? 'text-ink-500' : 'text-ink-700' }} hover:text-primary-800">
                                                <span class="inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full border {{ $isCompleted ? 'bg-success-500 border-success-500' : 'border-ink-300 bg-white' }}">
                                                    @if ($isCompleted)
                                                        <svg class="h-2 w-2 text-white" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 8 7 12 13 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    @endif
                                                </span>
                                                <span class="truncate flex-1">{{ $section->title }}</span>
                                                <x-icon name="chevron-right" class="w-3.5 h-3.5 text-ink-300 opacity-0 group-hover/section:opacity-100 group-hover/section:text-primary-600 group-hover/section:translate-x-0.5 transition-all" />
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
@endif
