/*
 * 学習カレンダー 草グリッド (GitHub 風ヒートマップ)
 *
 * 受講生ダッシュボード右レール。`#lc-grass` の data 属性
 * (data-today='Y-m-d' / data-days='{"Y-m-d": 分, ...}' / data-months) を読み、
 * 日別学習時間の濃淡グリッド (列=週・行=曜日・月曜始まり) を描画する。
 * マスの濃淡 = その日の学習時間。ホバーでツールチップ表示。
 */

const WD_MON = ['月', '火', '水', '木', '金', '土', '日'];

const pad = (n) => String(n).padStart(2, '0');
const keyOf = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const parseDate = (s) => {
    const [y, m, d] = s.split('-').map(Number);
    const date = new Date(y, m - 1, d);
    date.setHours(0, 0, 0, 0);
    return date;
};
const addDays = (d, n) => {
    const x = new Date(d);
    x.setDate(x.getDate() + n);
    x.setHours(0, 0, 0, 0);
    return x;
};

// 学習時間 (分) → 濃淡レベル 0〜4
function level(m) {
    if (m <= 0) return 0;
    if (m <= 20) return 1;
    if (m <= 45) return 2;
    if (m <= 90) return 3;
    return 4;
}

function fmtDur(m) {
    if (m <= 0) return '学習なし';
    const h = Math.floor(m / 60);
    const mm = m % 60;
    return (h ? `${h}h ` : '') + `${mm}m`;
}

// ----- tooltip (単一の浮遊要素を使い回す) -----
let tip;
function ensureTip() {
    if (tip) return tip;
    tip = document.createElement('div');
    tip.className = 'lc-tip';
    document.body.appendChild(tip);
    return tip;
}
function showTip(el, d, minutes) {
    const dowName = WD_MON[(d.getDay() + 6) % 7];
    ensureTip();
    tip.innerHTML =
        `<b>${d.getMonth() + 1}月${d.getDate()}日 (${dowName})</b>` +
        `<span>${minutes > 0 ? `${fmtDur(minutes)} 学習` : '学習記録なし'}</span>`;
    const r = el.getBoundingClientRect();
    tip.style.opacity = '1';
    const tw = tip.offsetWidth;
    tip.style.left = `${Math.max(8, Math.min(window.innerWidth - tw - 8, r.left + r.width / 2 - tw / 2))}px`;
    tip.style.top = `${r.top - tip.offsetHeight - 8 + window.scrollY}px`;
}
function hideTip() {
    if (tip) tip.style.opacity = '0';
}

export function initLearningCalendar() {
    const host = document.getElementById('lc-grass');
    if (!host) return;

    const today = parseDate(host.dataset.today);
    let data = {};
    try {
        data = JSON.parse(host.dataset.days || '{}');
    } catch (e) {
        data = {};
    }
    const months = parseInt(host.dataset.months || '4', 10);
    const mins = (d) => data[keyOf(d)] ?? 0;

    const cell = 14;
    const gap = 3;
    const gutter = 18;

    // グリッド起点 = (今月 - months + 1) の月初が属する週の月曜
    const firstOfRange = new Date(today.getFullYear(), today.getMonth() - months + 1, 1);
    const offset = (firstOfRange.getDay() - 1 + 7) % 7;
    const gridStart = addDays(firstOfRange, -offset);
    const totalDays = Math.round((today - gridStart) / 86400000) + 1;
    const weeks = Math.ceil(totalDays / 7);

    // 曜日ガター (全曜日を表示)
    let gut = `<div class="lc-gut" style="gap:${gap}px;">`;
    for (let r = 0; r < 7; r++) {
        gut += `<span style="height:${cell}px;line-height:${cell}px;">${WD_MON[r]}</span>`;
    }
    gut += '</div>';

    // 月ラベル行 + 週列
    let monthRow = '';
    let cols = '';
    let lastMonth = -1;
    for (let w = 0; w < weeks; w++) {
        const colStart = addDays(gridStart, w * 7);

        let labelMonth = -1;
        for (let r = 0; r < 7; r++) {
            const d = addDays(colStart, r);
            if (d.getDate() === 1 && d <= today) {
                labelMonth = d.getMonth();
                break;
            }
        }
        let lbl = '';
        if (labelMonth !== -1 && labelMonth !== lastMonth) {
            lbl = `${labelMonth + 1}月`;
            lastMonth = labelMonth;
        }
        monthRow += `<span class="lc-mlabel" style="width:${cell}px;">${lbl}</span>`;

        let col = `<div class="lc-wcol" style="gap:${gap}px;">`;
        for (let r = 0; r < 7; r++) {
            const dd = addDays(colStart, r);
            if (dd < gridStart || dd > today) {
                col += `<span class="lc-cell empty-slot" style="width:${cell}px;height:${cell}px;border-radius:4px;"></span>`;
            } else {
                const lv = level(mins(dd));
                const isToday = dd.getTime() === today.getTime();
                col += `<span class="lc-cell lv${lv}${isToday ? ' is-today' : ''}" data-k="${keyOf(dd)}" style="width:${cell}px;height:${cell}px;border-radius:4px;"></span>`;
            }
        }
        col += '</div>';
        cols += col;
    }

    // 凡例 (少 → 多)
    let legend = '';
    for (let i = 0; i <= 4; i++) {
        legend += `<span class="lc-cell lv${i}" style="width:11px;height:11px;border-radius:3px;"></span>`;
    }

    host.innerHTML =
        '<div class="lc-grass-scroll"><div class="lc-grass-inner">' +
        `<div class="lc-month-row" style="gap:${gap}px;padding-left:${gutter + gap}px;">${monthRow}</div>` +
        `<div class="lc-grass-body" style="gap:${gap}px;">${gut}` +
        `<div class="lc-cols" style="gap:${gap}px;">${cols}</div>` +
        '</div>' +
        '</div></div>' +
        '<div class="lc-legend"><span class="lc-legend-note">マスの濃さ = その日の学習時間</span>' +
        `<span class="lc-legend-scale"><span>少</span>${legend}<span>多</span></span></div>`;

    host.querySelectorAll('.lc-cell[data-k]').forEach((el) => {
        el.addEventListener('mouseenter', () => {
            const [y, m, d] = el.getAttribute('data-k').split('-').map(Number);
            const date = new Date(y, m - 1, d);
            showTip(el, date, mins(date));
        });
        el.addEventListener('mouseleave', hideTip);
    });
}
