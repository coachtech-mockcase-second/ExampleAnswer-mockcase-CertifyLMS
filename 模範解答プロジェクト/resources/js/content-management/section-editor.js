import { postJson } from '../utils/fetch-json.js';

function initEditor(root) {
    const input = root.querySelector('[data-editor-input]');
    const preview = root.querySelector('[data-editor-preview]');
    const counter = root.querySelector('[data-editor-counter]');
    const status = root.querySelector('[data-editor-status]');
    const endpoint = root.dataset.previewEndpoint;

    if (!input || !preview || !endpoint) {
        return;
    }

    let timer = null;
    let lastBody = null;

    const updateCounter = () => {
        if (counter) {
            counter.textContent = String(input.value.length);
        }
    };

    const updatePreview = async () => {
        const body = input.value;
        if (body === lastBody) {
            return;
        }
        lastBody = body;

        if (!body.trim()) {
            preview.innerHTML = '<p class="text-ink-400">入力するとプレビューがここに表示されます。</p>';
            if (status) status.textContent = '待機中';
            return;
        }

        if (status) status.textContent = '更新中…';

        try {
            const data = await postJson(endpoint, { body });
            preview.innerHTML = data.html ?? '';
            if (status) status.textContent = '更新済';
        } catch (e) {
            if (status) status.textContent = 'プレビュー失敗';
        }
    };

    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(updatePreview, 400);
    };

    input.addEventListener('input', () => {
        updateCounter();
        schedule();
    });

    updateCounter();
    updatePreview();
}

document.querySelectorAll('.section-editor').forEach(initEditor);
