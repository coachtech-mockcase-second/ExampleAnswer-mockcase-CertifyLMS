/**
 * 模試受験中の解答逐次保存スクリプト。
 *
 * 仕様:
 * - `[data-quiz-autosave-root]` のコンテナに紐づくラジオボタン(`[data-quiz-autosave]`) の change イベントを捕捉
 * - 同問題のラジオが選ばれるたびに PATCH `{autosave-url}` を呼び出して保存
 * - 各問題の `[data-save-status="{question_id}"]` に保存状態を反映(保存中 → 保存済)
 * - ナビゲーター(`[data-nav-item="{question_id}"]`) の見た目(解答済バッジ) を切替
 * - 「N / 80 解答済」表示(`[data-answered-count]`, `[data-answered-summary]`) を更新
 *
 * 失敗時はインラインメッセージで再送を促す(自動リトライはしない、本番形式の慎重さ重視)。
 */

(function () {
    const root = document.querySelector('[data-quiz-autosave-root]');
    if (!root) {
        return;
    }

    const url = root.dataset.autosaveUrl;
    const csrf = root.dataset.csrf;
    if (!url || !csrf) {
        return;
    }

    const answeredSet = new Set();
    root.querySelectorAll('[data-quiz-autosave]:checked').forEach((input) => {
        answeredSet.add(input.dataset.questionId);
    });
    refreshSummary();

    root.addEventListener('change', async (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || target.dataset.quizAutosave === undefined) {
            return;
        }

        const questionId = target.dataset.questionId;
        const optionId = target.dataset.optionId;
        if (!questionId || !optionId) {
            return;
        }

        const statusEl = document.querySelector(`[data-save-status="${questionId}"]`);
        if (statusEl) {
            statusEl.textContent = '保存中...';
            statusEl.className = 'text-warning-600';
        }

        // ラジオボタンの視覚状態反映
        const card = target.closest('label')?.parentElement;
        if (card) {
            card.querySelectorAll('label').forEach((lbl) => {
                lbl.classList.remove('border-primary-600', 'bg-primary-50');
                lbl.classList.add('border-ink-200');
            });
            const selectedLabel = target.closest('label');
            if (selectedLabel) {
                selectedLabel.classList.remove('border-ink-200');
                selectedLabel.classList.add('border-primary-600', 'bg-primary-50');
            }
        }

        try {
            const response = await fetch(url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    mock_exam_question_id: questionId,
                    selected_option_id: optionId,
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            const answeredAt = data.answered_at ? new Date(data.answered_at) : new Date();
            if (statusEl) {
                statusEl.textContent = `自動保存済 · ${formatTime(answeredAt)}`;
                statusEl.className = 'text-ink-500';
            }

            answeredSet.add(questionId);
            const navItem = document.querySelector(`[data-nav-item="${questionId}"]`);
            if (navItem) {
                navItem.classList.remove('bg-white', 'border-ink-200', 'text-ink-700');
                navItem.classList.add('bg-success-100', 'border-success-300', 'text-success-800');
            }
            refreshSummary();
        } catch (error) {
            if (statusEl) {
                statusEl.textContent = '保存に失敗しました。もう一度選択してください。';
                statusEl.className = 'text-danger-600';
            }
        }
    });

    function refreshSummary() {
        const count = answeredSet.size;
        const total = root.querySelectorAll('[data-nav-item]').length;

        document.querySelectorAll('[data-answered-count]').forEach((el) => {
            el.textContent = String(count);
        });
        document.querySelectorAll('[data-answered-summary]').forEach((el) => {
            el.textContent = `${count} / ${total}`;
        });
    }

    function formatTime(date) {
        return date.toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    }
})();
