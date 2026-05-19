/**
 * 管理者お知らせ作成フォーム: target_type ラジオに連動して
 * target_certification_id / target_user_id の入力パネルを表示切替する。
 */

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-announcement-target-fields]');
    if (root === null) return;

    const radios = root.querySelectorAll('[data-announcement-target-radio]');
    const panels = root.querySelectorAll('[data-announcement-target-panel]');

    function syncPanels(selectedType) {
        panels.forEach((panel) => {
            const targetType = panel.getAttribute('data-announcement-target-panel');
            panel.classList.toggle('hidden', targetType !== selectedType);
        });
    }

    radios.forEach((radio) => {
        radio.addEventListener('change', () => {
            if (radio.checked) {
                syncPanels(radio.value);
            }
        });
    });
});
