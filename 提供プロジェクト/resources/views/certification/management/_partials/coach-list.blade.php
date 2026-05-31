{{--
    資格詳細画面の担当コーチ一覧カード partial。
    構成: ヘッダ(見出し + 人数 + 追加ボタン) → コーチ行リスト(アバター + 名前 / メール + 解除ボタン、0 名時はプレースホルダ)
    フロント観点: 追加は data-modal-trigger でモーダルを開く(JS あり)。解除は行内の POST フォーム送信(@method('DELETE'))。
--}}
<x-card padding="lg" shadow="sm">
    <div class="flex items-center justify-between gap-2">
        <div>
            <h2 class="text-base font-semibold text-ink-900">担当コーチ</h2>
            <p class="text-xs text-ink-500 mt-1">{{ $certification->coaches->count() }} 名のコーチが担当</p>
        </div>
        @can('attachCoach', $certification)
            @if ($assignableCoaches->isNotEmpty())
                <x-button variant="primary" size="sm" data-modal-trigger="assign-coach-modal">
                    <x-icon name="plus" class="w-4 h-4" />
                    コーチを追加
                </x-button>
            @endif
        @endcan
    </div>

    @if ($certification->coaches->isEmpty())
        <div class="mt-6 text-sm text-ink-500 text-center py-6">
            まだコーチが割り当てられていません。
        </div>
    @else
        <ul class="mt-4 divide-y divide-subtle">
            @foreach ($certification->coaches as $coach)
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-avatar :src="$coach->avatar_url" :name="$coach->name ?? '?'" size="sm" />
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-ink-900 truncate">{{ $coach->name ?? '(未設定)' }}</div>
                            <div class="text-xs text-ink-500 font-mono truncate">{{ $coach->email }}</div>
                        </div>
                    </div>
                    @can('detachCoach', $certification)
                        <form novalidate method="POST" action="{{ route('admin.certifications.coaches.detach', ['certification' => $certification, 'coach' => $coach]) }}" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" size="sm">
                                <x-icon name="x-mark" class="w-4 h-4" />
                                解除
                            </x-button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
