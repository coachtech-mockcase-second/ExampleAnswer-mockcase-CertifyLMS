{{--
    読了完了モーダル（Section 読了直後に表示する祝福ダイアログ）。show.blade.php からのみ include。
    構成: ヘッダ（タイトル + 閉じる）→ メッセージ → アクション（次の Section へ / 演習へ / Chapter 一覧へ戻る）
    共通モーダル JS で制御（data-modal + data-auto-open で読み込み時に自動オープン、Esc / 閉じるボタンで閉じる）
--}}
<div
    id="sectionCompletedModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="sectionCompletedTitle"
    data-modal
    data-auto-open
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink-900/60 backdrop-blur-sm">
    <div class="w-full max-w-md overflow-hidden rounded-3xl bg-surface-raised shadow-lg">
        <header class="border-b border-subtle px-6 py-4 flex items-center justify-between">
            <h2 id="sectionCompletedTitle" class="text-base font-semibold text-ink-900">
                🎉 読了おめでとう！
            </h2>
            <button type="button" data-modal-close="sectionCompletedModal" aria-label="閉じる" class="text-ink-500 hover:text-ink-900">
                <x-icon name="x-mark" class="w-5 h-5" />
            </button>
        </header>

        <div class="px-6 py-5">
            <p class="text-sm text-ink-700">
                {{ $section->title }} を読了しました。次の学習にもいきましょう。
            </p>

            <div class="mt-5 space-y-2">
                @if ($nextSection)
                    <x-link-button :href="route('learning.sections.show', $nextSection)" variant="primary" class="w-full justify-center">
                        次の Section へ ・ {{ $nextSection->title }}
                    </x-link-button>
                @endif

                @if ($hasSectionQuestions)
                    <x-button variant="secondary" disabled class="w-full justify-center">
                        Section 紐づき問題演習へ
                    </x-button>
                @endif

                <x-link-button :href="route('learning.chapters.show', $chapter)" variant="ghost" class="w-full justify-center">
                    Chapter 一覧へ戻る
                </x-link-button>
            </div>
        </div>
    </div>
</div>
