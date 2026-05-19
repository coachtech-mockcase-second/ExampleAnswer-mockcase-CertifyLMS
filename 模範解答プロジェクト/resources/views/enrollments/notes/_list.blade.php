@php
    use App\Models\EnrollmentNote;

    $notes = $enrollment->notes()->with('author')->orderByDesc('created_at')->get();
@endphp

<x-card padding="md" shadow="sm">
    <x-slot:header>コーチメモ</x-slot:header>

    @can('create', [EnrollmentNote::class, $enrollment])
        <form method="POST" action="{{ route('admin.enrollments.notes.store', $enrollment) }}" class="space-y-3 pb-4 border-b border-ink-100">
            @csrf
            <x-form.textarea
                name="body"
                label="新規メモ"
                :rows="3"
                :value="old('body')"
                :error="$errors->first('body')"
                :maxlength="2000"
            />
            <x-button type="submit" variant="primary" size="sm">
                <x-icon name="plus" class="w-4 h-4" />
                追加
            </x-button>
        </form>
    @endcan

    @if ($notes->isEmpty())
        <p class="text-sm text-ink-500 pt-4">まだメモがありません。</p>
    @else
        <ul class="pt-4 space-y-3">
            @foreach ($notes as $note)
                <li class="p-3 rounded-md border border-ink-100">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 text-xs text-ink-500">
                            <x-avatar :src="$note->author?->avatar_url" :name="$note->author?->name ?? '?'" size="sm" />
                            <span class="font-semibold text-ink-700">{{ $note->author?->name ?? '不明' }}</span>
                            <span class="tabular-nums">{{ $note->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                        @can('delete', $note)
                            <form
                                method="POST"
                                action="{{ route('enrollment-notes.destroy', $note) }}"
                                onsubmit="return confirm('このメモを削除しますか？');"
                            >
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="ghost" size="sm">
                                    <x-icon name="trash" class="w-4 h-4" />
                                </x-button>
                            </form>
                        @endcan
                    </div>
                    <div class="mt-2 text-sm text-ink-800 whitespace-pre-line">{{ $note->body }}</div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
