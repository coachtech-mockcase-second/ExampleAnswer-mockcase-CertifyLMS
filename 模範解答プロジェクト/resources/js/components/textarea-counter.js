/**
 * Textarea 文字数カウンタ (リアルタイム)。
 * <textarea maxlength="N" data-textarea-counter>
 * 親要素内の [data-textarea-counter-current] が現在値を表示。
 */
export function initTextareaCounter() {
    document.querySelectorAll('[data-textarea-counter]').forEach((textarea) => {
        const wrapper = textarea.closest('.relative');
        const display = wrapper?.querySelector('[data-textarea-counter-current]');
        if (!display) return;

        const update = () => {
            display.textContent = textarea.value.length;
        };

        textarea.addEventListener('input', update);
        update();
    });
}
