/**
 * 面談予約画面のカレンダー + 時刻スロット選択。
 *
 * 1 つの `[data-meeting-calendar]` を起点に:
 *   - その月の日付グリッドを描画(prev/next 月の余白セル含む)
 *   - 日付クリックで `data-availability-endpoint?date=YYYY-MM-DD` を fetch して 60 分単位スロットを描画
 *   - スロット選択で hidden `scheduled_at` を更新 + 送信ボタンを活性化
 *
 * Spec: 60 分固定、コーチ自動割当、available_coach_count > 0 のスロットのみ選択可。
 */

import { getJson } from '../utils/fetch-json';

const WEEKDAY_JA = ['日', '月', '火', '水', '木', '金', '土'];

function pad2(n) {
    return n.toString().padStart(2, '0');
}

function ymd(date) {
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

function isSameDay(a, b) {
    return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

function isPast(date, today) {
    return date < new Date(today.getFullYear(), today.getMonth(), today.getDate());
}

class SlotPicker {
    constructor(root) {
        this.root = root;
        this.endpoint = root.dataset.availabilityEndpoint;
        this.titleEl = root.querySelector('[data-cal-title]');
        this.gridEl = root.querySelector('[data-cal-grid]');
        this.prevBtn = root.querySelector('[data-cal-prev]');
        this.nextBtn = root.querySelector('[data-cal-next]');
        this.todayBtn = root.querySelector('[data-cal-today]');

        this.form = document.getElementById('meeting-store-form');
        this.slotsRoot = this.form?.querySelector('[data-slots-grid]');
        this.slotsTitle = this.form?.querySelector('[data-slots-title]');
        this.slotsEmpty = this.form?.querySelector('[data-slots-empty]');
        this.selectionLabel = this.form?.querySelector('[data-selection-label]');
        this.scheduledInput = this.form?.querySelector('[data-scheduled-input]');
        this.submitButton = this.form?.querySelector('[data-submit-button]');

        this.today = new Date();
        this.cursor = new Date(this.today.getFullYear(), this.today.getMonth(), 1);
        this.selectedDate = null;
        this.selectedSlot = null;
        this.slotCache = new Map();

        this.bindEvents();
        this.renderCalendar();
    }

    bindEvents() {
        this.prevBtn?.addEventListener('click', () => this.shiftMonth(-1));
        this.nextBtn?.addEventListener('click', () => this.shiftMonth(1));
        this.todayBtn?.addEventListener('click', () => {
            this.cursor = new Date(this.today.getFullYear(), this.today.getMonth(), 1);
            this.renderCalendar();
            this.selectDate(this.today);
        });
    }

    shiftMonth(delta) {
        this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + delta, 1);
        this.renderCalendar();
    }

    renderCalendar() {
        if (!this.titleEl || !this.gridEl) {
            return;
        }
        this.titleEl.textContent = `${this.cursor.getFullYear()} 年 ${this.cursor.getMonth() + 1} 月`;
        this.gridEl.innerHTML = '';

        const firstDay = new Date(this.cursor.getFullYear(), this.cursor.getMonth(), 1);
        const startOffset = firstDay.getDay();
        const daysInMonth = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, 0).getDate();

        for (let i = 0; i < startOffset; i++) {
            const fillerDate = new Date(firstDay);
            fillerDate.setDate(firstDay.getDate() - (startOffset - i));
            this.gridEl.appendChild(this.buildDayCell(fillerDate, true));
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(this.cursor.getFullYear(), this.cursor.getMonth(), d);
            this.gridEl.appendChild(this.buildDayCell(date, false));
        }

        const totalShown = startOffset + daysInMonth;
        const trailing = (7 - (totalShown % 7)) % 7;
        for (let i = 1; i <= trailing; i++) {
            const filler = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, i);
            this.gridEl.appendChild(this.buildDayCell(filler, true));
        }
    }

    buildDayCell(date, outOfMonth) {
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.textContent = date.getDate();

        const past = isPast(date, this.today);
        const isToday = isSameDay(date, this.today);
        const isSelected = this.selectedDate && isSameDay(date, this.selectedDate);

        const baseClasses = 'aspect-square flex items-center justify-center rounded-md text-sm tabular-nums transition relative';
        const stateClasses = outOfMonth
            ? 'text-ink-300 cursor-not-allowed'
            : past
                ? 'text-ink-300 cursor-not-allowed line-through'
                : isSelected
                    ? 'bg-primary-600 text-white font-bold shadow-md'
                    : isToday
                        ? 'bg-warning-100 text-warning-900 font-semibold hover:bg-warning-200'
                        : 'text-ink-700 hover:bg-primary-50 border border-transparent hover:border-primary-200';

        cell.className = `${baseClasses} ${stateClasses}`;

        if (outOfMonth || past) {
            cell.disabled = true;
        } else {
            cell.addEventListener('click', () => this.selectDate(date));
        }
        return cell;
    }

    async selectDate(date) {
        this.selectedDate = new Date(date);
        this.selectedSlot = null;
        this.renderCalendar();
        this.updateSelection();

        if (!this.endpoint || !this.slotsRoot) {
            return;
        }

        const key = ymd(date);
        if (this.slotsTitle) {
            this.slotsTitle.textContent = `${date.getMonth() + 1}月${date.getDate()}日 (${WEEKDAY_JA[date.getDay()]}) の空き枠`;
        }
        this.slotsRoot.innerHTML = '<div class="col-span-2 text-sm text-ink-500 py-4 text-center">空き枠を取得中...</div>';
        this.slotsEmpty?.classList.add('hidden');

        try {
            const slots = this.slotCache.has(key)
                ? this.slotCache.get(key)
                : (await getJson(`${this.endpoint}?date=${encodeURIComponent(key)}`)).slots ?? [];
            this.slotCache.set(key, slots);
            this.renderSlots(slots);
        } catch (error) {
            this.slotsRoot.innerHTML = `<div class="col-span-2 text-sm text-danger-600 py-4 text-center">空き枠の取得に失敗しました。再度お試しください。</div>`;
        }
    }

    renderSlots(slots) {
        if (!this.slotsRoot) {
            return;
        }
        this.slotsRoot.innerHTML = '';
        if (slots.length === 0) {
            this.slotsEmpty?.classList.remove('hidden');
            return;
        }
        this.slotsEmpty?.classList.add('hidden');

        slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            const start = new Date(slot.slot_start);
            const end = new Date(slot.slot_end);
            const isAvailable = slot.available_coach_count > 0;

            const label = `${pad2(start.getHours())}:${pad2(start.getMinutes())} 〜 ${pad2(end.getHours())}:${pad2(end.getMinutes())}`;
            const hint = `空きコーチ ${slot.available_coach_count} 名`;

            button.innerHTML = `<div class="font-display text-sm font-bold tabular-nums">${label}</div><div class="text-[10px] mt-0.5">${hint}</div>`;

            const baseClasses = 'rounded-md border-2 px-3 py-2 text-center transition w-full';
            if (!isAvailable) {
                button.className = `${baseClasses} bg-ink-50 text-ink-300 border-[var(--border-subtle)] line-through cursor-not-allowed`;
                button.disabled = true;
            } else if (this.selectedSlot && this.selectedSlot.slot_start === slot.slot_start) {
                button.className = `${baseClasses} bg-primary-600 text-white border-primary-600 shadow-md`;
            } else {
                button.className = `${baseClasses} bg-surface-raised text-ink-900 border-[var(--border-subtle)] hover:border-primary-400`;
            }

            if (isAvailable) {
                button.addEventListener('click', () => this.selectSlot(slot));
            }
            this.slotsRoot.appendChild(button);
        });
    }

    selectSlot(slot) {
        this.selectedSlot = slot;
        if (this.selectedDate) {
            const cachedSlots = this.slotCache.get(ymd(this.selectedDate));
            if (cachedSlots) {
                this.renderSlots(cachedSlots);
            }
        }
        this.updateSelection();
    }

    updateSelection() {
        if (!this.selectionLabel || !this.scheduledInput || !this.submitButton) {
            return;
        }
        if (!this.selectedSlot) {
            this.selectionLabel.textContent = '日時を選択してください';
            this.scheduledInput.value = '';
            this.submitButton.disabled = true;
            return;
        }
        const start = new Date(this.selectedSlot.slot_start);
        const end = new Date(this.selectedSlot.slot_end);
        const displayDate = `${start.getFullYear()}/${pad2(start.getMonth() + 1)}/${pad2(start.getDate())}`;
        const dow = WEEKDAY_JA[start.getDay()];
        this.selectionLabel.textContent = `${displayDate} (${dow}) ${pad2(start.getHours())}:${pad2(start.getMinutes())} 〜 ${pad2(end.getHours())}:${pad2(end.getMinutes())}`;

        // Laravel が `after:now` + regex で受け取れる形式: `YYYY-MM-DDTHH:00:00`
        this.scheduledInput.value = `${ymd(start)}T${pad2(start.getHours())}:00:00`;
        this.submitButton.disabled = false;
    }
}

function initSlotPickers() {
    document.querySelectorAll('[data-meeting-calendar]').forEach((root) => {
        new SlotPicker(root);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSlotPickers);
} else {
    initSlotPickers();
}
