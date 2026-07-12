@extends('layouts.app')

@section('title', $conversation->title)

@php
    /** @var \App\Models\AiChatConversation $conversation */
    $historyToday = [];
    $history7days = [];
    $history30days = [];
    $historyAll = auth()->user()->aiChatConversations()
        ->with(['enrollment.certification', 'section'])
        ->orderByDesc('last_message_at')
        ->limit(30)
        ->get();
    foreach ($historyAll as $h) {
        if ($h->last_message_at?->isToday()) {
            $historyToday[] = $h;
        } elseif ($h->last_message_at?->greaterThan(now()->subDays(7))) {
            $history7days[] = $h;
        } else {
            $history30days[] = $h;
        }
    }
    $model = (string) config('ai-chat.gemini.model', 'gemini-2.5-flash');
@endphp

@section('content')
    {{--
        AI 相談のフル画面。左に会話履歴サイドバー、右に 1 会話分のやり取り。
        構成: パンくず → 2 カラム（履歴サイドバー[今日/過去7日/過去30日でグルーピング] / 会話カラム[ヘッダ → メッセージ一覧 → 入力欄]）→ タイトル変更モーダル → 新規メッセージ用 <template>。
        フロント観点: メッセージ送受信は素の JS で非同期（@vite の index.js）、受信文は <template> を clone して描画。タイトル編集・新規会話はモーダル（data-modal-trigger）。会話削除はフォーム POST + confirm()。
    --}}
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'AI 相談', 'href' => route('ai-chat.index')],
        ['label' => \Illuminate\Support\Str::limit($conversation->title, 24)],
    ]" />

    <div class="mt-4 grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] bg-surface-raised border border-subtle rounded-3xl overflow-hidden shadow-sm h-[calc(100vh-180px)] min-h-[560px]">
        {{-- History sidebar --}}
        <aside class="hidden lg:flex flex-col bg-[#FCFEFD] border-r border-subtle overflow-y-auto">
            <div class="px-5 pt-5 pb-3 flex items-baseline gap-2">
                <h2 class="font-display font-bold text-base text-ink-900 tracking-tight">AI 相談</h2>
                <span class="ml-auto text-[11px] text-ink-500 tabular-nums">{{ number_format($historyAll->count()) }} 会話</span>
            </div>

            <button type="button" data-modal-trigger="new-ai-chat-modal"
                class="mx-3.5 mb-3 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-[11px] bg-secondary-600 hover:bg-secondary-700 text-white text-sm font-semibold shadow-md transition">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                新しい会話
            </button>

            <div class="px-2.5 pb-4 flex flex-col gap-0.5">
                @foreach (['今日' => $historyToday, '過去 7 日' => $history7days, '過去 30 日' => $history30days] as $label => $items)
                    @if (! empty($items))
                        <div class="text-[10px] font-bold tracking-wider uppercase text-ink-400 px-2.5 pt-3 pb-1">{{ $label }}</div>
                        @foreach ($items as $h)
                            <a href="{{ route('ai-chat.conversations.show', $h) }}"
                                class="flex flex-col gap-1 px-3 py-2 rounded-[10px] {{ $h->id === $conversation->id ? 'bg-secondary-50' : 'hover:bg-ink-50' }} transition no-underline">
                                <div class="text-xs font-semibold line-clamp-2 leading-snug
                                    {{ $h->id === $conversation->id ? 'text-secondary-900' : 'text-ink-800' }}">
                                    {{ $h->title }}
                                </div>
                                <div class="text-[10px] text-ink-500">
                                    @if ($h->section?->title)
                                        <span class="px-1.5 py-0.5 rounded bg-secondary-50 text-secondary-800 font-medium">📚 {{ \Illuminate\Support\Str::limit($h->section->title, 16) }}</span>
                                    @elseif ($h->enrollment?->certification?->name)
                                        <span class="px-1.5 py-0.5 rounded bg-primary-50 text-primary-800 font-medium">🎓 {{ $h->enrollment->certification->name }}</span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded bg-ink-50 text-ink-600 font-medium">全般</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </div>
        </aside>

        {{-- Conversation column --}}
        <div class="flex flex-col overflow-hidden bg-[#FCFEFD]">
            <header class="px-6 py-3 backdrop-blur-sm bg-white/70 border-b border-subtle flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="font-display font-bold text-base text-ink-900 tracking-tight truncate" data-conversation-title>
                            {{ $conversation->title }}
                        </div>
                        <button type="button"
                            class="w-6 h-6 rounded-md text-ink-400 hover:bg-ink-50 hover:text-secondary-700 inline-flex items-center justify-center flex-shrink-0"
                            data-modal-trigger="rename-ai-chat-modal"
                            aria-label="タイトルを編集">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-3 h-3" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                            </svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        @include('ai-chat._partials.context-badges')
                        <span class="text-[10px] text-ink-500 font-mono">{{ $model }}</span>
                    </div>
                </div>

                <div class="ml-auto flex gap-1.5">
                    <form novalidate method="POST" action="{{ route('ai-chat.conversations.destroy', $conversation) }}"
                        onsubmit="return confirm('この会話を削除しますか? 履歴は残りません。');"
                        class="inline-flex">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="w-8 h-8 rounded-[9px] border border-subtle text-ink-600 hover:border-danger-300 hover:text-danger-700 inline-flex items-center justify-center"
                            aria-label="この会話を削除">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="w-3.5 h-3.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </header>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto px-8 pt-6 pb-3" data-message-scroller>
                @if ($conversation->messages->isEmpty())
                    <div class="max-w-[760px] mx-auto text-center text-sm text-ink-500 py-12">
                        まだメッセージはありません。下から最初の質問を送ってみよう。
                    </div>
                @else
                    @include('ai-chat._partials.message-list')
                @endif
            </div>

            {{-- Composer --}}
            <div class="px-6 py-4 backdrop-blur-sm bg-white/70 border-t border-subtle">
                @include('ai-chat._partials.input-form')
            </div>
        </div>
    </div>

    {{-- Rename modal --}}
    <x-modal id="rename-ai-chat-modal" title="タイトルを編集" size="sm" :auto-open="$errors->has('title')">
        <x-slot:body>
            <form novalidate method="POST" action="{{ route('ai-chat.conversations.update', $conversation) }}" id="rename-ai-chat-form">
                @csrf
                @method('PATCH')
                <x-form.input
                    name="title"
                    label="タイトル"
                    :value="old('title', $conversation->title)"
                    :error="$errors->first('title')"
                    :required="true"
                    :maxlength="100"
                />
            </form>
        </x-slot:body>
        <x-slot:footer>
            <x-button variant="ghost" data-modal-close="rename-ai-chat-modal">キャンセル</x-button>
            <x-button variant="primary" type="submit" form="rename-ai-chat-form">保存</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Template for new messages (JS が clone する) --}}
    <template data-ai-chat-message-template>
        <li class="flex gap-3" data-message-id="" data-message-status="">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white text-[11px] font-bold flex-shrink-0" data-avatar></span>
            <div class="flex-1 min-w-0" data-body>
                <div class="text-[11px] font-bold text-ink-700 mb-1 px-0.5" data-author></div>
                <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed" data-bubble>
                    <div class="whitespace-pre-wrap break-words" data-message-content></div>
                </div>
                <div class="text-[11px] text-ink-400 mt-1 px-0.5 tabular-nums" data-time></div>
            </div>
        </li>
    </template>

    @include('ai-chat._partials.new-conversation-modal')
@endsection

@push('scripts')
    @vite('resources/js/ai-chat/index.js')
@endpush
