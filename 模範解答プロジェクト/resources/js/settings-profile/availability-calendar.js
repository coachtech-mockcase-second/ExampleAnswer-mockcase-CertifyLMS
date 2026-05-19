// 面談設定タブの週次カレンダー(1 時間粒度)。
// - 単純クリックは 1 時間枠(start = HH:00, end = HH+1:00)としてモーダルを開く
// - 同一曜日内でのドラッグ選択は anchor の時刻 〜 drag 終端 + 1 時間 をモーダルに反映する

function formatHhmm(hour, minute) {
    return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
}

function prefillCreateModal(dayOfWeek, startHour, endHour) {
    const modal = document.getElementById('availability-create-modal');
    if (!modal) {
        return;
    }
    const day = modal.querySelector('select[name="day_of_week"]');
    const start = modal.querySelector('input[name="start_time"]');
    const end = modal.querySelector('input[name="end_time"]');
    const active = modal.querySelector('input[name="is_active"]');

    if (day) {
        day.value = String(dayOfWeek);
    }
    if (start) {
        start.value = formatHhmm(startHour, 0);
    }
    if (end) {
        end.value = formatHhmm(Math.min(endHour, 24), 0);
    }
    if (active) {
        active.checked = true;
    }
}

function openCreateModal() {
    const trigger = document.querySelector('[data-modal-trigger="availability-create-modal"]');
    if (trigger instanceof HTMLElement) {
        trigger.click();
    }
}

function bindCalendar() {
    const calendar = document.querySelector('[data-availability-calendar]');
    if (!calendar) {
        return;
    }
    const cells = Array.from(calendar.querySelectorAll('[data-availability-cell]'));
    if (cells.length === 0) {
        return;
    }

    let anchorCell = null;
    let lastHoveredCell = null;
    let isDragging = false;

    const clearHighlights = () => {
        cells.forEach((c) => c.classList.remove('bg-primary-100', 'ring-1', 'ring-primary-500'));
    };

    const highlightRange = (anchor, current) => {
        if (!anchor || !current || anchor.dataset.dayOfWeek !== current.dataset.dayOfWeek) {
            return;
        }
        const min = Math.min(Number(anchor.dataset.hour), Number(current.dataset.hour));
        const max = Math.max(Number(anchor.dataset.hour), Number(current.dataset.hour));
        clearHighlights();
        cells.forEach((c) => {
            if (c.dataset.dayOfWeek !== anchor.dataset.dayOfWeek) {
                return;
            }
            const h = Number(c.dataset.hour);
            if (h >= min && h <= max) {
                c.classList.add('bg-primary-100', 'ring-1', 'ring-primary-500');
            }
        });
    };

    cells.forEach((cell) => {
        cell.addEventListener('mousedown', (event) => {
            if (event.button !== 0) {
                return;
            }
            event.preventDefault();
            anchorCell = cell;
            lastHoveredCell = cell;
            isDragging = true;
            highlightRange(anchorCell, cell);
        });

        cell.addEventListener('mouseenter', () => {
            if (!isDragging || !anchorCell) {
                return;
            }
            if (cell.dataset.dayOfWeek !== anchorCell.dataset.dayOfWeek) {
                return;
            }
            lastHoveredCell = cell;
            highlightRange(anchorCell, cell);
        });
    });

    const finishDrag = () => {
        if (!isDragging || !anchorCell) {
            return;
        }
        isDragging = false;
        const endCell = lastHoveredCell ?? anchorCell;
        clearHighlights();

        const dayOfWeek = Number(anchorCell.dataset.dayOfWeek);
        const startHour = Math.min(Number(anchorCell.dataset.hour), Number(endCell.dataset.hour));
        const endHourInclusive = Math.max(Number(anchorCell.dataset.hour), Number(endCell.dataset.hour));
        // 単純クリック / ドラッグ問わず、終端セルの次の 1 時間境界までを枠とする
        const endHour = endHourInclusive + 1;

        prefillCreateModal(dayOfWeek, startHour, endHour);
        anchorCell = null;
        lastHoveredCell = null;
        openCreateModal();
    };

    document.addEventListener('mouseup', finishDrag);
    document.addEventListener('mouseleave', () => {
        if (isDragging) {
            clearHighlights();
            isDragging = false;
            anchorCell = null;
            lastHoveredCell = null;
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindCalendar);
} else {
    bindCalendar();
}
