{{--
    どの画面の右下にも出せる AI 相談の浮遊ウィジェット（FAB + ミニチャットパネル）。
    構成: 丸い起動ボタン(FAB) → パネル[ヘッダ(新規/フル画面/閉じる) → コンテキストバッジ → メッセージ領域(ウェルカム + サジェスト) → 入力欄]。
    フロント観点: 開閉・メッセージの非同期送受信・サジェストのタップ入力はすべて素の JS（data-* フックで制御、パネルは hidden/flex を class で切替）。⌘+Enter 送信。Markdown 応答は JS で整形描画。
    props: sectionId・sectionTitle（教材から開いた時の文脈）/ certificationName（資格文脈）。いずれも無ければ「全般相談」表示。
--}}
@props([
    'sectionId' => null,
    'sectionTitle' => null,
    'certificationName' => null,
])

@php
    $model = (string) config('ai-chat.gemini.model', 'gemini-2.5-flash');

    if ($sectionId && $sectionTitle) {
        $ctxLabel = '📚 '.$sectionTitle;
        $ctxClass = 'bg-secondary-50 border-secondary-100 text-secondary-800';
        $ctxType = 'section';
    } elseif ($certificationName) {
        $ctxLabel = '🎓 '.$certificationName;
        $ctxClass = 'bg-primary-50 border-primary-100 text-primary-800';
        $ctxType = 'cert';
    } else {
        $ctxLabel = '全般相談';
        $ctxClass = 'bg-ink-50 border-ink-100 text-ink-700';
        $ctxType = 'general';
    }
@endphp

<div data-ai-chat-widget
    data-section-id="{{ $sectionId }}"
    data-section-title="{{ $sectionTitle }}"
    data-certification-name="{{ $certificationName }}"
    data-store-url="{{ route('ai-chat.conversations.store') }}"
    data-fullscreen-base-url="{{ url('/ai-chat/conversations') }}">

    {{-- FAB --}}
    <button type="button"
        aria-label="AI 相談を開く"
        data-ai-chat-fab
        class="fixed right-5 bottom-5 z-[200] w-[60px] h-[60px] rounded-full bg-gradient-tropic text-ink-900 shadow-xl hover:-translate-y-0.5 hover:scale-[1.04] transition inline-flex items-center justify-center group">
        <span class="absolute inset-0 rounded-full pointer-events-none animate-ping bg-secondary-300/30"></span>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="w-6 h-6 relative" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z"/>
        </svg>
    </button>

    {{-- Panel: hidden / flex を JS で切替 (HTML hidden 属性は display:flex に上書きされるため、Tailwind class で制御) --}}
    <div data-ai-chat-panel
        role="dialog"
        aria-modal="false"
        aria-labelledby="ai-chat-widget-title"
        aria-hidden="true"
        inert
        class="fixed right-5 bottom-5 sm:right-5 sm:bottom-5 inset-3 sm:inset-auto z-[200] sm:w-[380px] sm:h-[600px] sm:max-h-[calc(100vh-100px)] bg-white rounded-3xl shadow-2xl hidden flex-col overflow-hidden font-sans text-ink-900 origin-bottom-right">

        {{-- Header --}}
        <div class="flex items-center gap-2.5 px-4 py-3.5 bg-gradient-tropic text-ink-900">
            <div class="w-9 h-9 rounded-xl bg-white/60 text-secondary-700 inline-flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="w-4.5 h-4.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                </svg>
            </div>
            <div>
                <div id="ai-chat-widget-title" class="font-display font-bold text-[15px] leading-tight tracking-tight">🤖 AI 相談</div>
                <div class="text-[11px] opacity-70 mt-px">問題で詰まった瞬間の補助線</div>
            </div>
            <div class="ml-auto flex gap-1">
                <button type="button"
                    data-ai-chat-new
                    aria-label="新しい相談を始める"
                    title="新しい相談を始める"
                    class="w-[30px] h-[30px] rounded-[9px] bg-white/40 hover:bg-white/75 text-ink-900 inline-flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                </button>
                <button type="button"
                    data-ai-chat-fullscreen
                    aria-label="フル画面で開く"
                    title="フル画面で開く"
                    class="w-[30px] h-[30px] rounded-[9px] bg-white/40 hover:bg-white/75 text-ink-900 inline-flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                    </svg>
                </button>
                <button type="button"
                    data-ai-chat-close
                    aria-label="閉じる"
                    title="閉じる"
                    class="w-[30px] h-[30px] rounded-[9px] bg-white/40 hover:bg-white/75 text-ink-900 inline-flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Context badge --}}
        <div class="flex items-center gap-2 px-4 py-2.5 bg-surface-canvas border-b border-subtle text-xs">
            <span data-ai-chat-context class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-white border {{ $ctxClass }} font-semibold">
                {{ $ctxLabel }}
            </span>
            <span class="ml-auto text-[10px] text-ink-500 font-mono">{{ $model }}</span>
        </div>

        {{-- Messages --}}
        <div role="log"
            aria-live="polite"
            aria-atomic="false"
            data-ai-chat-messages
            class="flex-1 px-4 py-4 overflow-y-auto bg-surface-canvas flex flex-col gap-3">
            <div class="flex gap-2 max-w-[86%]" data-ai-chat-welcome>
                <span class="w-[26px] h-[26px] rounded-full bg-secondary-600 text-white inline-flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3" aria-hidden="true">
                        <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
                    </svg>
                </span>
                <div>
                    <div class="bg-white rounded-[13px] border border-subtle px-3.5 py-2.5 text-[13px] leading-relaxed text-ink-900">
                        <p class="m-0 mb-1.5">こんにちは {{ auth()->user()->name }}さん 👋
                            @if ($ctxType === 'section') いま読んでいる教材について追加で質問できます。
                            @elseif ($ctxType === 'cert') この資格について学習相談できます。
                            @else 気になることを何でも聞いてください。
                            @endif
                        </p>
                        <p class="m-0">下から入力するか、サジェストをタップ。</p>
                    </div>

                    <div class="flex flex-col gap-1.5 mt-2" data-ai-chat-suggestions>
                        @if ($ctxType === 'section')
                            <button type="button" data-ai-chat-suggestion="このセクションの要点を 3 行でまとめて"
                                class="flex items-start gap-2 px-3 py-2 bg-white border border-subtle rounded-[11px] text-xs text-ink-700 hover:border-secondary-300 hover:bg-secondary-50/40 text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-3.5 h-3.5 text-secondary-600 flex-shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                                要点を 3 行でまとめて
                            </button>
                            <button type="button" data-ai-chat-suggestion="この概念を中学生でもわかるように説明して"
                                class="flex items-start gap-2 px-3 py-2 bg-white border border-subtle rounded-[11px] text-xs text-ink-700 hover:border-secondary-300 hover:bg-secondary-50/40 text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-3.5 h-3.5 text-secondary-600 flex-shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/></svg>
                                かみくだいて説明して
                            </button>
                            <button type="button" data-ai-chat-suggestion="このセクションから試験頻出のパターンを教えて"
                                class="flex items-start gap-2 px-3 py-2 bg-white border border-subtle rounded-[11px] text-xs text-ink-700 hover:border-secondary-300 hover:bg-secondary-50/40 text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-3.5 h-3.5 text-secondary-600 flex-shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z"/></svg>
                                試験頻出パターンは?
                            </button>
                        @else
                            <button type="button" data-ai-chat-suggestion="今日の学習内容を整理したい"
                                class="flex items-start gap-2 px-3 py-2 bg-white border border-subtle rounded-[11px] text-xs text-ink-700 hover:border-secondary-300 hover:bg-secondary-50/40 text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-3.5 h-3.5 text-secondary-600 flex-shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                                今日の学習を整理したい
                            </button>
                            <button type="button" data-ai-chat-suggestion="苦手分野の克服アプローチを教えて"
                                class="flex items-start gap-2 px-3 py-2 bg-white border border-subtle rounded-[11px] text-xs text-ink-700 hover:border-secondary-300 hover:bg-secondary-50/40 text-left">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-3.5 h-3.5 text-secondary-600 flex-shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z"/></svg>
                                苦手分野の克服法
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Composer --}}
        <div class="bg-white border-t border-subtle px-3 py-2.5">
            <div class="flex gap-1.5 items-end bg-surface-canvas border border-subtle rounded-[14px] p-1 focus-within:border-secondary-400 focus-within:bg-white focus-within:shadow-[0_0_0_3px_rgba(124,58,237,0.12)] transition">
                <textarea
                    data-ai-chat-widget-input
                    rows="1"
                    maxlength="2000"
                    placeholder="気になることを入力..."
                    class="flex-1 bg-transparent border-0 outline-none resize-none text-[13px] leading-relaxed text-ink-900 placeholder:text-ink-400 py-2 px-2.5 max-h-[96px] min-h-[36px] rounded-[11px]"
                    aria-label="AI への質問入力"></textarea>
                <button type="button"
                    data-ai-chat-widget-send
                    aria-label="送信"
                    class="w-8 h-8 rounded-[10px] bg-secondary-600 hover:bg-secondary-700 text-white inline-flex items-center justify-center self-center disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="w-3.5 h-3.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                </button>
            </div>
            <div class="flex items-center mt-1.5 px-1 text-[10px] text-ink-500">
                <span>⌘+Enter で送信</span>
            </div>
        </div>
    </div>
</div>
