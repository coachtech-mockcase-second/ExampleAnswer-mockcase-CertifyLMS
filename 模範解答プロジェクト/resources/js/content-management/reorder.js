import { patchJson } from '../utils/fetch-json.js';

/**
 * 簡易 reorder: HTML5 drag-and-drop で並び替え → PATCH エンドポイントへ {ids: [...]} を送る。
 * 受講生講師向けの軽量実装で、SortableJS 等の外部依存は使わない。
 */
function initReorder(list) {
    const endpoint = list.dataset.reorderEndpoint;
    if (!endpoint) return;

    const items = list.querySelectorAll('[data-reorder-id]');
    items.forEach((item) => {
        item.setAttribute('draggable', 'true');
        item.classList.add('cursor-grab');

        item.addEventListener('dragstart', (e) => {
            item.classList.add('opacity-50');
            e.dataTransfer?.setData('text/plain', item.dataset.reorderId ?? '');
        });

        item.addEventListener('dragend', () => {
            item.classList.remove('opacity-50');
            submitOrder(list, endpoint);
        });

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            const dragging = list.querySelector('.opacity-50');
            if (!dragging || dragging === item) return;
            const rect = item.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            if (after) {
                item.after(dragging);
            } else {
                item.before(dragging);
            }
        });
    });
}

async function submitOrder(list, endpoint) {
    const ids = Array.from(list.querySelectorAll('[data-reorder-id]'))
        .map((el) => el.dataset.reorderId)
        .filter(Boolean);

    try {
        await patchJson(endpoint, { ids });
    } catch (e) {
        console.warn('並び替え失敗', e);
    }
}

document.querySelectorAll('[data-reorder-endpoint]').forEach(initReorder);
