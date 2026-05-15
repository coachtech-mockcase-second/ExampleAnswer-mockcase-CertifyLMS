function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function upload(endpoint, file) {
    const fd = new FormData();
    fd.append('file', file);

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: fd,
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        const message = payload.message ?? `HTTP ${response.status}`;
        const error = new Error(message);
        error.status = response.status;
        throw error;
    }

    return response.json();
}

function insertAtCursor(textarea, snippet) {
    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? textarea.value.length;
    textarea.value = textarea.value.slice(0, start) + snippet + textarea.value.slice(end);
    const pos = start + snippet.length;
    textarea.selectionStart = pos;
    textarea.selectionEnd = pos;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function initUploader(root) {
    const input = root.querySelector('[data-upload-input]');
    const status = root.querySelector('[data-upload-status]');
    const endpoint = root.dataset.uploadEndpoint;

    if (!input || !endpoint) {
        return;
    }

    input.addEventListener('change', async () => {
        const file = input.files?.[0];
        if (!file) return;

        if (status) {
            status.textContent = `${file.name} をアップロード中…`;
            status.className = 'mt-2 text-xs text-ink-500';
        }

        try {
            const data = await upload(endpoint, file);
            const markdown = `![${data.alt_placeholder ?? '画像'}](${data.url})\n`;
            const textarea = document.querySelector('[data-editor-input]');
            if (textarea) {
                insertAtCursor(textarea, markdown);
            }
            if (status) {
                status.textContent = `アップロード完了: ${data.url}`;
                status.className = 'mt-2 text-xs text-success-700';
            }
            input.value = '';
        } catch (e) {
            if (status) {
                status.textContent = `失敗: ${e.message}`;
                status.className = 'mt-2 text-xs text-danger-600';
            }
        }
    });
}

document.querySelectorAll('.image-uploader').forEach(initUploader);
