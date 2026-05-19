// アバター画像アップロードのクライアント側 MIME / サイズ検証。
// サーバ側 FormRequest と二重化することで、不正画像送信を未然に防ぎ即時フィードバックする。
// 不正時はサーバ送信を抑止して隣接 description にエラーメッセージを表示する。

const MAX_SIZE_BYTES = 2 * 1024 * 1024;
const ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp'];

function showError(input, message) {
    let target = input.parentElement?.querySelector('[data-avatar-client-error]');
    if (!target) {
        target = document.createElement('p');
        target.dataset.avatarClientError = '';
        target.className = 'mt-1 text-xs text-danger-700';
        target.setAttribute('role', 'alert');
        input.parentElement?.appendChild(target);
    }
    target.textContent = message;
}

function clearError(input) {
    const target = input.parentElement?.querySelector('[data-avatar-client-error]');
    if (target) {
        target.textContent = '';
    }
}

function validate(file) {
    if (!ALLOWED_MIME.includes(file.type)) {
        return 'アバター画像は PNG / JPEG / WebP のいずれかで指定してください。';
    }
    if (file.size > MAX_SIZE_BYTES) {
        return 'アバター画像は 2MB 以下で指定してください。';
    }
    return null;
}

document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('[data-avatar-form]');
    forms.forEach((form) => {
        const input = form.querySelector('[data-avatar-input]');
        const submit = form.querySelector('[data-avatar-submit]');
        if (!input || !submit) {
            return;
        }

        input.addEventListener('change', () => {
            clearError(input);
            const file = input.files?.[0];
            if (!file) {
                return;
            }
            const error = validate(file);
            if (error) {
                showError(input, error);
                input.value = '';
            }
        });

        form.addEventListener('submit', (event) => {
            const file = input.files?.[0];
            if (!file) {
                event.preventDefault();
                showError(input, 'アバター画像を選択してください。');
                return;
            }
            const error = validate(file);
            if (error) {
                event.preventDefault();
                showError(input, error);
            }
        });
    });
});
