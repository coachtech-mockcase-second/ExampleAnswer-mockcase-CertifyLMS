/**
 * Question 作成・編集フォームで「正答」ラジオに連動して
 * hidden の options[*][is_correct] を 1/0 に同期する。
 */
function sync() {
    const radios = document.querySelectorAll('input[name="correct_option"]');
    radios.forEach((radio) => {
        const wrap = radio.closest('div.flex');
        if (!wrap) return;
        const hidden = wrap.querySelector('input[name$="[is_correct]"][type="hidden"]');
        if (hidden) {
            hidden.value = radio.checked ? '1' : '0';
        }
    });
}

document.addEventListener('change', (e) => {
    if (e.target && e.target.matches('input[name="correct_option"]')) {
        sync();
    }
});

document.addEventListener('DOMContentLoaded', sync);
