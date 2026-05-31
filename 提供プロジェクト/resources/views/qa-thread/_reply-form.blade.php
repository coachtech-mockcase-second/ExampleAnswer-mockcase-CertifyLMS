{{-- 回答投稿フォーム。回答できないユーザー（管理者など）には案内メッセージを表示 --}}
@php
    $canReply = auth()->user()?->can('create', [\App\Models\QaReply::class, $thread]) ?? false;
@endphp

@if ($canReply)
    <x-card padding="md" shadow="sm" class="border border-primary-100/60">
        <form novalidate method="POST" action="{{ route('qa-board.replies.store', $thread) }}" class="flex flex-col gap-3">
            @csrf
            <x-form.textarea
                name="body"
                label="回答を投稿"
                :rows="5"
                :value="old('body')"
                :error="$errors->first('body')"
                :maxlength="5000"
                :required="true"
                placeholder="質問者と他の受講生に向けた回答を書きましょう。"
            />
            <div class="flex items-center justify-end gap-3">
                <x-button type="submit" variant="primary">
                    <x-icon name="paper-airplane" class="w-4 h-4" />
                    回答を投稿
                </x-button>
            </div>
        </form>
    </x-card>
@elseif (auth()->user()?->role === \App\Enums\UserRole::Admin)
    <div class="bg-ink-50 border border-subtle rounded-2xl px-5 py-4 text-sm text-ink-600">
        管理者は閲覧 + モデレーション削除のみ可能で、回答投稿はできません。
    </div>
@endif
