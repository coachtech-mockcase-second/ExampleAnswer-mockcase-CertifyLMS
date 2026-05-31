{{--
    回答 1 件分の表示ブロック（$reply を受け取る）。
    構成: ヘッダ（アバター + 投稿者名 + コーチ/自分バッジ + 日時）+ 操作（編集リンク + 削除フォーム）→ 本文。
    フロント観点: JS なし。編集は専用ページへリンク遷移、削除はフォーム POST + confirm()。本文は e() + nl2br() で XSS 対策。
--}}
@php
    // 回答 1 件分の表示ブロック（$reply を受け取る）
    $viewer = auth()->user();
    $isAuthor = $viewer !== null && $reply->user_id === $viewer->id;
    $canUpdate = $viewer?->can('update', $reply) ?? false;
    $canDelete = $viewer?->can('delete', $reply) ?? false;
    $isAdminContext ??= request()->routeIs('admin.*');
    $destroyRoute = $isAdminContext ? 'admin.qa-board.replies.destroy' : 'qa-board.replies.destroy';
@endphp

<article id="reply-{{ $reply->id }}" class="bg-surface-raised border border-subtle rounded-2xl px-5 py-4">
    <header class="flex items-start gap-3">
        <x-avatar :src="$reply->user?->avatar_url" :name="$reply->user?->name ?? '?'" size="md" />
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-ink-900 text-sm">{{ $reply->user?->name ?? '不明' }}</span>
                @if ($reply->user?->role === \App\Enums\UserRole::Coach)
                    <x-badge variant="info" size="sm">コーチ</x-badge>
                @endif
                @if ($isAuthor)
                    <x-badge variant="gray" size="sm">自分の回答</x-badge>
                @endif
            </div>
            <div class="text-[11px] text-ink-500 font-mono mt-0.5">
                {{ $reply->created_at?->format('Y/m/d H:i') }}
                @if ($reply->updated_at && $reply->updated_at->ne($reply->created_at))
                    <span class="ml-2">(編集済: {{ $reply->updated_at->diffForHumans() }})</span>
                @endif
            </div>
        </div>

        {{-- 操作ボタン（編集リンク + 削除フォーム）。ドロップダウンは使わず直接並べる --}}
        <div class="flex items-center gap-1.5 shrink-0">
            {{-- 編集はリンク遷移 --}}
            @if ($canUpdate)
                <x-link-button
                    href="{{ route('qa-board.replies.edit', ['thread' => $reply->qa_thread_id, 'reply' => $reply->id]) }}"
                    variant="ghost"
                    size="sm"
                >
                    <x-icon name="pencil-square" class="w-4 h-4" />
                    編集
                </x-link-button>
            @endif
            {{-- 削除はフォーム送信 + confirm() --}}
            @if ($canDelete)
                <form novalidate
                    method="POST"
                    action="{{ route($destroyRoute, ['thread' => $reply->qa_thread_id, 'reply' => $reply->id]) }}"
                    onsubmit="return confirm('この回答を削除しますか？');"
                >
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="ghost" size="sm">
                        <x-icon name="trash" class="w-4 h-4" />
                        削除
                    </x-button>
                </form>
            @endif
        </div>
    </header>

    {{-- 本文。e() でエスケープ後 nl2br() で改行を <br> 化（XSS 対策）--}}
    <div class="mt-3 text-sm leading-relaxed text-ink-800">
        {!! nl2br(e($reply->body)) !!}
    </div>
</article>
