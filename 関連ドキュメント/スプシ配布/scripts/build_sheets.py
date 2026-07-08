#!/usr/bin/env python3
"""マスタスプレッドシート生成ツール（issue #67 / 30%スプシ運用）

リポジトリの md（SSoT）から LMS 登録用マスタスプシ 2 枚を生成・再生成する:

  - 要件シート  = `要件シート_詳細度30%/概要.md` + manifest（チケット Doc リンク）
      → タブ「シート1 概要」「シート2 チケット一覧」
  - 評価シート  = `評価シート.md`
      → タブ「総合評価」「Story」「Bug」「Task」「横断品質」「誤答リスト」

デザインは既存コース（BookShelf / ContactForm）のマスタに準拠:
紺ヘッダー（要件 #002060 / 評価 #073763）+ 白太字 / 20pt グレータイトル + 二重下線バンド /
要件系は左端スペーサー列のインデントレイアウト / 全表セル細罫線 / 評価詳細タブは 9pt + ヘッダー固定 /
総合評価は「配点・正答数・正答率・スコア・評価・コメント」のハウス 7 列構成。

コマンド:
  build requirement|evaluation|all   スプシを生成 / 全面再生成（冪等。ID は manifest に記録し URL 不変）
  links-sync                         チケット一覧のタイトル列 HYPERLINK を manifest と突合して差し替え
  verify                             スプシの集計値と md の集計を検算
  place                              Docs トップフォルダ + スプシ 2 枚を雛形シートフォルダ直下へ移動
  share --role reader|writer         スプシ 2 枚へのリンク共有付与（明示実行専用）

生成は「派生ビューの全面書き直し」であり、スプシ上の手編集は保持されない（md を直して再 build する）。
セットアップ・運用ルールは ../README.md と ../運用ガイド.md を参照。
"""
from __future__ import annotations

import argparse
import re
import sys
from datetime import datetime, timezone

from sync_docs import (
    OPS_DIR,
    TICKETS_DIR,
    get_creds,
    hyperlink_formula,
    load_manifest,
    save_manifest,
)

GAIYOU_PATH = TICKETS_DIR / "概要.md"
EVAL_PATH = OPS_DIR.parent / "評価シート.md"

REQ_TITLE = "模擬案件_CertifyLMS_要件シート（詳細度30%）"
EVAL_TITLE = "模擬案件_CertifyLMS_評価シート"
HINAGATA_FOLDER_ID = "1sHg7fOV4AydQySUAHPFAZdWaXjEIEuel"  # 雛形シート（既存コースのマスタ置き場）

TAB_REQ_GAIYOU = "シート1 概要"
TAB_REQ_TICKETS = "シート2 チケット一覧"
TAB_SUMMARY = "総合評価"
TAB_STORY = "Story"
TAB_BUG = "Bug"
TAB_TASK = "Task"
TAB_CROSS = "横断品質"
TAB_WRONG = "誤答リスト"

# タブは sheetId を固定して冪等に上書きする
SHEET_IDS = {
    TAB_REQ_GAIYOU: 101, TAB_REQ_TICKETS: 102,
    TAB_SUMMARY: 201, TAB_STORY: 202, TAB_BUG: 203, TAB_TASK: 204,
    TAB_CROSS: 205, TAB_WRONG: 206,
}

EVAL_TAB_OF_TYPE = {"S": TAB_STORY, "B": TAB_BUG, "T": TAB_TASK}


# ---------------------------------------------------------------- 色・セル
def color(hexstr: str) -> dict:
    return {
        "red": int(hexstr[0:2], 16) / 255,
        "green": int(hexstr[2:4], 16) / 255,
        "blue": int(hexstr[4:6], 16) / 255,
    }


REQ_NAVY = color("073763")     # 要件系の基調（評価シートと同じ青に統一・ラベル文字/列見出し）
EVAL_NAVY = color("073763")    # 評価系ヘッダー（BookShelf 評価シート準拠）
TITLE_GRAY = color("3F3F3F")   # 20pt タイトル
WHITE = color("FFFFFF")
NOTE_FG = color("6B7280")
SUBTOTAL_BG = color("EFEFEF")
BLOCK_BAND = color("C9D4E8")   # 要件シートのブロック見出し帯（淡い紺）
LABEL_BG = color("E4EAF4")     # 要件シートの項目ラベル（淡い青グレー・従来の紺ベタを軽量化）
COMMENT_BG = color("FFFDF5")   # 採点者記入欄（うっすらクリーム）
TYPE_BG = {"Story": color("DBEAFE"), "Bug": color("FEE2E2"), "Task": color("FEF3C7")}
TAB_COLORS = {
    TAB_REQ_GAIYOU: color("002060"), TAB_REQ_TICKETS: color("16A34A"),
    TAB_SUMMARY: color("073763"), TAB_STORY: color("16A34A"), TAB_BUG: color("DC2626"),
    TAB_TASK: color("D97706"), TAB_CROSS: color("7C3AED"), TAB_WRONG: color("6B7280"),
}

THIN = {"style": "SOLID"}
BOX = {"top": THIN, "bottom": THIN, "left": THIN, "right": THIN}
DOUBLE_BOTTOM = {"bottom": {"style": "DOUBLE"}}


def cell(value=None, formula=None, *, bold=False, bg=None, fg=None, wrap=True,
         percent=False, italic=False, size=None, align=None, borders=None, box=False):
    c: dict = {}
    if formula is not None:
        c["userEnteredValue"] = {"formulaValue": formula}
    elif isinstance(value, bool):
        c["userEnteredValue"] = {"boolValue": value}
    elif isinstance(value, (int, float)):
        c["userEnteredValue"] = {"numberValue": value}
    elif value is not None:
        c["userEnteredValue"] = {"stringValue": str(value)}
    fmt: dict = {"wrapStrategy": "WRAP" if wrap else "OVERFLOW_CELL",
                 "verticalAlignment": "TOP"}
    tf: dict = {}
    if bold:
        tf["bold"] = True
    if italic:
        tf["italic"] = True
    if fg:
        tf["foregroundColor"] = fg
    if size:
        tf["fontSize"] = size
    if tf:
        fmt["textFormat"] = tf
    if bg:
        fmt["backgroundColor"] = bg
    if align:
        fmt["horizontalAlignment"] = align
    if percent:
        fmt["numberFormat"] = {"type": "PERCENT", "pattern": "0.0%"}
    if box:
        fmt["borders"] = BOX
    elif borders:
        fmt["borders"] = borders
    c["userEnteredFormat"] = fmt
    return c


def blank_row(n=1):
    return [{"values": [cell("")]} for _ in range(n)]


def indent(cells):
    """要件系タブの左端スペーサー列（ハウスレイアウト準拠）。"""
    return [cell("")] + cells


def title_band(title: str, desc: str, n_cols: int = None) -> tuple[list[dict], list[tuple]]:
    """ドキュメント見出し: 20pt タイトル + サブタイトルを列 A に配置（枠線・二重線なし）。

    テキストは列 A から OVERFLOW で右へ流し、二重下線バンドや余分な罫線は使わない
    （ブロック見出しの青文字が構造を担うためタイトル直下の区切り線は不要）。"""
    return [
        {"values": [cell(title, bold=True, size=20, fg=TITLE_GRAY, wrap=False)]},
        {"values": [cell(desc, fg=NOTE_FG, wrap=False)]},
        {"values": [cell("")]},
    ], []


# ---------------------------------------------------------------- md パース
def md_text(s: str) -> str:
    """md セル/行 → スプシ用プレーンテキスト。"""
    s = s.replace("<br>", "\n")
    s = re.sub(r"\*\*(.+?)\*\*", r"\1", s)
    s = s.replace("`", "")
    return s.strip()


def parse_md_table(lines: list[str]) -> list[list[str]]:
    rows = []
    for line in lines:
        line = line.strip()
        if not line.startswith("|"):
            continue
        cells = [c.strip() for c in line.strip("|").split("|")]
        if all(re.fullmatch(r":?-+:?", c) for c in cells if c):
            continue  # 区切り行
        rows.append(cells)
    return rows


def split_sections(text: str, level: int) -> list[tuple[str, list[str]]]:
    """指定レベルの見出しで (見出しテキスト, 本文行list) に分割。先頭の見出し前は捨てない（"" キー）。"""
    marker = "#" * level + " "
    sections: list[tuple[str, list[str]]] = [("", [])]
    for line in text.split("\n"):
        if line.startswith(marker):
            sections.append((line[len(marker):].strip(), []))
        else:
            sections[-1][1].append(line)
    return sections


# ---------------------------------------------------------------- 要件シート
def parse_gaiyou():
    text = GAIYOU_PATH.read_text(encoding="utf-8")
    h1 = text.split("\n", 1)[0].lstrip("# ").strip()
    sheets = {name: body for name, body in split_sections(text, 2)}
    g_key = next(k for k in sheets if k.startswith("シート1"))
    t_key = next(k for k in sheets if k.startswith("シート2"))

    # シート1: 概要 — ### セクションごとの表（開発プロセスは #### PR節 を内包）
    gaiyou_sections = []
    for name, body in split_sections("\n".join(sheets[g_key]), 3):
        if not name:
            continue
        main_body = split_sections("\n".join(body), 4)
        table = parse_md_table(main_body[0][1])
        subs = [(n, parse_md_table(b), [l for l in b if l.strip().startswith(">")])
                for n, b in main_body[1:]]
        gaiyou_sections.append((name, table, subs))

    # シート2: チケット一覧 — 導入文 + 種別ブロック + サマリ
    lead_lines = []
    tsec = split_sections("\n".join(sheets[t_key]), 3)
    for line in tsec[0][1]:
        line = line.strip()
        if line and not line.startswith("|"):
            lead_lines.append(md_text(line))
    blocks = []
    summary = None
    for name, body in tsec[1:]:
        table = parse_md_table(body)
        note = next((md_text(l.lstrip("> ").strip()) for l in body if l.strip().startswith("> ※")), None)
        if name.startswith("件数"):
            summary = (name, table)
        else:
            blocks.append((name, table, note))
    return {"h1": h1, "gaiyou": gaiyou_sections, "lead": lead_lines,
            "blocks": blocks, "summary": summary}


REQ_INTRO = ("本書は課題全体のガイドです。個別チケットの要件は「シート2 チケット一覧」の"
             "各チケット（タイトルのリンク先ドキュメント）を参照してください。")
LEAD_DETAIL_REWRITE = "各チケットの詳細は、タイトルのリンク先（チケット詳細ドキュメント）を参照してください。"


def build_requirement_tabs(manifest) -> list[dict]:
    data = parse_gaiyou()

    # ---- シート1 概要（スペーサー列 + ラベル=紺 / 値=罫線のハウスレイアウト）
    N = 3  # A スペーサー / B 項目 / C 内容
    rows, merges_g = title_band("Certify LMS 開発参画", REQ_INTRO, N)
    for name, table, subs in data["gaiyou"]:
        # ブロック見出し: 大きめの青文字を列 A に置き（背景バンドなし）、配下の項目を列 B へインデント
        rows.append({"values": [cell(name, bold=True, size=15, fg=REQ_NAVY, wrap=False)]})
        HEADER_PAIRS = {("項目", "内容"), ("セクション", "書くこと"), ("ステップ", "内容")}
        for item, value in table:
            if (item, value) in HEADER_PAIRS:
                continue  # ラベル/値レイアウトではヘッダー行は冗長（ハウス準拠）
            rows.append({"values": indent([
                cell(md_text(item), bold=True, bg=REQ_NAVY, fg=WHITE, box=True),
                cell(md_text(value), box=True)])})
        for sub_name, sub_table, sub_notes in subs:
            rows += blank_row()
            # サブ見出し: ブロックより 1 段インデント（列 B）した青文字
            rows.append({"values": indent([
                cell(sub_name, bold=True, size=13, fg=REQ_NAVY, wrap=False)])})
            for c1, c2 in sub_table:
                if (c1, c2) in HEADER_PAIRS:
                    continue
                rows.append({"values": indent([
                    cell(md_text(c1), bold=True, bg=REQ_NAVY, fg=WHITE, box=True),
                    cell(md_text(c2), box=True)])})
            for note in sub_notes:
                merges_g.append((len(rows), len(rows) + 1, 1, N))
                rows.append({"values": indent([cell(md_text(note.lstrip("> ").strip()),
                                                    fg=NOTE_FG, italic=True)])})
        rows += blank_row()
    gaiyou_tab = {
        "title": TAB_REQ_GAIYOU, "rows": rows, "col_widths": [28, 190, 870],
        "merges": merges_g, "col_count": N, "frozen": 0,
    }

    # ---- シート2 チケット一覧（スペーサー列 + 種別ブロック + 紺ヘッダー行）
    N = 6  # A スペーサー / B ID / C タイトル / D サブカテゴリ / E 難易度 / F 依存チケット
    lead = data["lead"]
    desc = lead[0] if lead else ""
    rows, merges_t = title_band("チケット一覧", desc, N)
    for line in lead[1:]:
        if line.startswith("- 各チケットの詳細は"):
            line = "- " + LEAD_DETAIL_REWRITE
        merges_t.append((len(rows), len(rows) + 1, 1, N))
        rows.append({"values": indent([cell(line, fg=TITLE_GRAY)])})
    rows += blank_row()

    warn_missing = []
    for name, table, note in data["blocks"]:
        # 種別見出し: 大きめの青文字を列 A に置き、配下の表を列 B へインデント
        rows.append({"values": [cell(name, bold=True, size=15, fg=REQ_NAVY, wrap=False)]})
        header, *body = table
        rows.append({"values": indent([cell(md_text(h), bold=True, bg=REQ_NAVY, fg=WHITE, box=True)
                                       for h in header[:-1]])})  # 末尾のチケット詳細列は Doc リンクに置換
        for r in body:
            ticket_id = md_text(r[0])
            entry = manifest["tickets"].get(ticket_id)
            if entry and entry.get("docUrl"):
                title_cell = cell(formula=hyperlink_formula(entry, ticket_id), box=True)
            else:
                warn_missing.append(ticket_id)
                title_cell = cell(md_text(r[1]), box=True)
            rows.append({"values": indent([
                cell(ticket_id, align="CENTER", box=True), title_cell,
                cell(md_text(r[2]), box=True), cell(md_text(r[3]), align="CENTER", box=True),
                cell(md_text(r[4]), box=True),
            ])})
        if note:
            merges_t.append((len(rows), len(rows) + 1, 1, N))
            rows.append({"values": indent([cell(note, fg=NOTE_FG, italic=True)])})
        rows += blank_row()
    if warn_missing:
        print(f"  ⚠ Doc 未生成のためタイトルをテキスト表示: {', '.join(warn_missing)}（push 後に再 build か links-sync）")

    name, table = data["summary"]
    rows.append({"values": [cell(name, bold=True, size=15, fg=REQ_NAVY, wrap=False)]})
    header, *body = table
    rows.append({"values": indent([cell(md_text(h), bold=True, bg=REQ_NAVY, fg=WHITE, box=True)
                                   for h in header])})
    for r in body:
        bold = r[0].startswith("**")
        rows.append({"values": indent([cell(md_text(c), bold=bold, box=True,
                                            bg=SUBTOTAL_BG if bold else None,
                                            align="CENTER" if i else None)
                                       for i, c in enumerate(r)])})
    tickets_tab = {
        "title": TAB_REQ_TICKETS, "rows": rows,
        "col_widths": [28, 80, 430, 190, 90, 240],
        "merges": merges_t, "col_count": N, "frozen": 0,
    }
    return [gaiyou_tab, tickets_tab]


# ---------------------------------------------------------------- 評価シート
def parse_eval():
    text = EVAL_PATH.read_text(encoding="utf-8")
    sections = {name: body for name, body in split_sections(text, 2)}

    buckets = {TAB_STORY: [], TAB_BUG: [], TAB_TASK: [], TAB_CROSS: []}
    block_counts: dict[str, tuple[int, float]] = {}
    for name, body in split_sections("\n".join(sections["チケット要件"]), 3):
        if not name:
            continue
        rows = parse_md_table(body)[1:]  # ヘッダ落とし
        if name.startswith("Story"):
            tab = TAB_STORY
        elif name.startswith("Bug"):
            tab = TAB_BUG
        else:
            tab = TAB_TASK
        parsed = [(md_text(r[0]), md_text(r[1]), md_text(r[2]), float(r[3])) for r in rows]
        buckets[tab] += parsed
        block_counts[name] = (len(parsed), sum(p[3] for p in parsed))
    cross_rows = parse_md_table(sections["横断品質"])[1:]
    buckets[TAB_CROSS] = [(md_text(r[0]), md_text(r[1]), md_text(r[2]), float(r[3])) for r in cross_rows]

    # 集計（md 側の期待値）
    agg = {}
    agg_tables = split_sections("\n".join(sections["集計"]), 3)
    for r in parse_md_table(agg_tables[0][1])[1:]:
        label = md_text(r[0]) + (" " + md_text(r[1]) if md_text(r[1]) else "")
        agg[label.strip()] = (int(md_text(r[2])), float(md_text(r[3])))
    ba = {}
    line_rows = []
    for name, body in agg_tables[1:]:
        if name.startswith("Basic"):
            for r in parse_md_table(body)[1:]:
                ba[md_text(r[0])] = (int(md_text(r[1])), float(md_text(r[2])))
        if name.startswith("評価ライン"):
            line_rows = [[md_text(c) for c in r] for r in parse_md_table(body)[1:]]
    return {"buckets": buckets, "block_counts": block_counts, "agg": agg,
            "ba": ba, "line_rows": line_rows}


def selfcheck_eval(data) -> None:
    """md 内部整合: 明細行の実合計と「集計」セクションの数値が一致するか。"""
    problems = []

    def chk(label, got_n, got_p, exp):
        if exp is None:
            problems.append(f"集計に「{label}」の行がない")
        elif (got_n, got_p) != (exp[0], float(exp[1])):
            problems.append(f"{label}: 明細 {got_n}項目/{got_p:g}点 ≠ 集計 {exp[0]}/{exp[1]:g}")

    bc = data["block_counts"]
    for label in ["Story（Basic）", "Story（Advance）", "Bug（Basic）", "Bug（Advance）",
                  "Task（Basic）", "Task（Advance）"]:
        n, p = bc[label]
        chk(f"チケット要件 {label}", n, p, data["agg"].get(f"チケット要件 {label}"))
    cross = data["buckets"][TAB_CROSS]
    for mid in ["コード品質", "テスト", "README", "PR 記述"]:
        rows = [r for r in cross if r[1] == mid]
        chk(f"横断品質 {mid}", len(rows), sum(r[3] for r in rows),
            data["agg"].get(f"横断品質 {mid}"))
    total_n = sum(len(v) for v in data["buckets"].values())
    total_p = sum(r[3] for v in data["buckets"].values() for r in v)
    chk("合計", total_n, total_p, data["agg"].get("合計"))

    cross_adv = sum(r[3] for r in cross if r[2].startswith("（応用）"))
    basic_p = sum(r[3] for k in [TAB_STORY, TAB_BUG, TAB_TASK] for r in data["buckets"][k]
                  if re.match(r"^[SBT]-B-", r[1])) + (sum(r[3] for r in cross) - cross_adv)
    adv_p = total_p - basic_p
    if data["ba"].get("Basic") and float(data["ba"]["Basic"][1]) != basic_p:
        problems.append(f"Basic 配点: 明細 {basic_p:g} ≠ 集計 {data['ba']['Basic'][1]}")
    if data["ba"].get("Advance") and float(data["ba"]["Advance"][1]) != adv_p:
        problems.append(f"Advance 配点: 明細 {adv_p:g} ≠ 集計 {data['ba']['Advance'][1]}")
    if problems:
        sys.exit("評価シート.md の明細と集計セクションが不整合です（先に md を直してください）:\n  "
                 + "\n  ".join(problems))
    print(f"  ✓ md 内部検算 OK: {total_n} 項目 / {total_p:g} 点"
          f"（Basic {basic_p:g} / Advance {adv_p:g}）")


def eval_hdr(text):
    return cell(text, bold=True, bg=EVAL_NAVY, fg=WHITE, box=True)


def detail_tab(title, rows4) -> dict:
    rows = [{"values": [cell(h, bold=True, bg=EVAL_NAVY, fg=WHITE, size=9, box=True)
                        for h in ["大項目", "中項目", "評価基準", "評価点", "可否", "点数"]]}]
    for i, (major, mid, crit, point) in enumerate(rows4):
        r = i + 2  # 1-indexed 行番号
        rows.append({"values": [
            cell(major, size=9, box=True), cell(mid, size=9, box=True),
            cell(crit, size=9, box=True),
            cell(point if point % 1 else int(point), size=9, align="CENTER", box=True),
            cell(False, size=9, align="CENTER", box=True),
            cell(formula=f"=IF(E{r},D{r},0)", size=9, align="CENTER", box=True),
        ]})
    return {
        "title": title, "rows": rows,
        "col_widths": [90, 155, 620, 55, 55, 55],
        "frozen": 1, "col_count": 6,
        "checkbox": (1, len(rows4) + 1, 4, 5),  # E2:E{n+1}
    }


GRADE_IFS = '=IFS(D{r}>=0.95,"S",D{r}>=0.85,"A",D{r}>=0.7,"B",D{r}>=0.5,"C",TRUE,"D")'


def build_evaluation_tabs() -> tuple[list[dict], dict]:
    data = parse_eval()
    selfcheck_eval(data)
    b = data["buckets"]

    def sumtabs(col, tabs):
        return "+".join(f"SUM('{t}'!{col}2:{col})" for t in tabs)

    def sumif(tab, crit_col, crit, col):
        return f"SUMIF('{tab}'!${crit_col}$2:${crit_col},{crit},'{tab}'!${col}$2:${col})"

    ALL_TABS = [TAB_STORY, TAB_BUG, TAB_TASK, TAB_CROSS]
    rows: list[dict] = []
    merges: list[tuple] = []

    def add(values):
        rows.append({"values": values})
        return len(rows)  # 1-indexed 行番号

    def header_row(first_label, with_comment=True):
        """ハウス流: ブロック見出しをヘッダー行の第 1 セルに置く紺行。"""
        labels = [first_label, "配点", "正答数", "正答率", "スコア", "評価"]
        labels.append("コメント" if with_comment else "")
        add([eval_hdr(h) for h in labels])

    def score_cells(r, points_formula, earned_formula, *, bold=False, bg=None):
        return [
            cell(formula=points_formula, bold=bold, bg=bg, align="CENTER", box=True),
            cell(formula=earned_formula, bold=bold, bg=bg, align="CENTER", box=True),
            cell(formula=f"=IFERROR(C{r}/B{r},0)", bold=bold, bg=bg, percent=True,
                 align="CENTER", box=True),
            cell(formula=f"=5*D{r}", bold=bold, bg=bg, align="CENTER", box=True),
            cell(formula=GRADE_IFS.format(r=r), bold=bold, bg=bg, align="CENTER", box=True),
            cell("", bg=bg, box=True),
        ]

    # ---- 総合
    header_row("総合")
    r_total = len(rows) + 1
    add([cell("総合", bold=True, box=True)]
        + score_cells(r_total, "=" + sumtabs("D", ALL_TABS), "=" + sumtabs("F", ALL_TABS), bold=True))
    rows += blank_row()

    # ---- 評価ライン
    add([eval_hdr(h) for h in
         ["評価ライン（COACHTECH 標準: S 95 / A 85 / B 70 / C 50）", "閾値", "必要点", "到達条件", "", "", ""]])
    merges.append((len(rows) - 1, len(rows), 3, 7))
    thresholds = {"S": 0.95, "A": 0.85, "B": 0.7, "C": 0.5}
    for line in data["line_rows"]:
        grade, cond = line[0], (line[3] if len(line) > 3 else "")
        merges.append((len(rows), len(rows) + 1, 3, 7))
        need = (cell(formula=f"=CEILING($B${r_total}*{thresholds[grade]},1)", align="CENTER", box=True)
                if grade in thresholds else cell("—", align="CENTER", box=True))
        add([cell(grade, bold=True, align="CENTER", box=True),
             cell(line[1], align="CENTER", box=True), need,
             cell(cond, fg=NOTE_FG, box=True)])
    rows += blank_row()

    # ---- 大項目別
    header_row("大項目別")
    for label, tabs in [("チケット要件", [TAB_STORY, TAB_BUG, TAB_TASK]), ("横断品質", [TAB_CROSS])]:
        r = len(rows) + 1
        add([cell(label, box=True)]
            + score_cells(r, "=" + sumtabs("D", tabs), "=" + sumtabs("F", tabs)))
    rows += blank_row()

    # ---- 種別・難易度別
    header_row("種別・難易度別")
    basic_rows_idx, adv_rows_idx = [], []
    for label, tab, prefix in [
        ("Story（Basic）", TAB_STORY, "S-B-*"), ("Story（Advance）", TAB_STORY, "S-A-*"),
        ("Bug（Basic）", TAB_BUG, "B-B-*"), ("Bug（Advance）", TAB_BUG, "B-A-*"),
        ("Task（Basic）", TAB_TASK, "T-B-*"), ("Task（Advance）", TAB_TASK, "T-A-*"),
    ]:
        r = len(rows) + 1
        (basic_rows_idx if "Basic" in label else adv_rows_idx).append(r)
        add([cell(label, box=True)]
            + score_cells(r, "=" + sumif(tab, "B", f'"{prefix}"', "D"),
                          "=" + sumif(tab, "B", f'"{prefix}"', "F")))
    cross_adv_d = sumif(TAB_CROSS, "C", '"（応用）*"', "D")
    cross_adv_f = sumif(TAB_CROSS, "C", '"（応用）*"', "F")
    r = len(rows) + 1
    basic_rows_idx.append(r)
    add([cell("横断品質（Basic）", box=True)]
        + score_cells(r, f"=SUM('{TAB_CROSS}'!D2:D)-{cross_adv_d}",
                      f"=SUM('{TAB_CROSS}'!F2:F)-{cross_adv_f}"))
    r = len(rows) + 1
    adv_rows_idx.append(r)
    add([cell("横断品質（応用）", box=True)]
        + score_cells(r, f"={cross_adv_d}", f"={cross_adv_f}"))
    for label, idx in [("Basic 計", basic_rows_idx), ("Advance 計", adv_rows_idx)]:
        r = len(rows) + 1
        add([cell(label, bold=True, bg=SUBTOTAL_BG, box=True)]
            + score_cells(r, "=" + "+".join(f"B{i}" for i in idx),
                          "=" + "+".join(f"C{i}" for i in idx), bold=True, bg=SUBTOTAL_BG))
    rows += blank_row()

    # ---- チケット別
    header_row("チケット別")
    mid_order: list[tuple[str, str]] = []
    for tab in ALL_TABS:
        seen = set()
        for _, mid, _, _ in b[tab]:
            if mid not in seen:
                seen.add(mid)
                mid_order.append((tab, mid))
    for tab, mid in mid_order:
        r = len(rows) + 1
        add([cell(mid, box=True)]
            + score_cells(r, "=" + sumif(tab, "B", f"$A{r}", "D"),
                          "=" + sumif(tab, "B", f"$A{r}", "F")))
    rows += blank_row()

    # ---- 補足（採点者フリー記述欄・空欄）
    merges.append((len(rows), len(rows) + 1, 0, 7))
    add([eval_hdr("補足（採点者記入欄）")])
    box_start = len(rows)
    for _ in range(6):
        add([cell("", bg=COMMENT_BG, box=True)] + [cell("", bg=COMMENT_BG, box=True) for _ in range(6)])
    merges.append((box_start, box_start + 6, 0, 7))  # 6 行 × 7 列を 1 つの記入枠に

    summary_tab = {
        "title": TAB_SUMMARY, "rows": rows,
        "col_widths": [250, 70, 70, 70, 70, 55, 460],
        "merges": merges, "col_count": 7, "frozen": 0,
    }

    detail_tabs = [detail_tab(t, b[t]) for t in ALL_TABS]

    wrong_query = (
        '=IFERROR(QUERY({' + ";".join(f"'{t}'!A2:F" for t in ALL_TABS) + "},"
        '"select Col2, Col3, Col4 where Col5 = false and Col2 is not null",0),'
        '"（未達成の項目はありません）")'
    )
    wrong_tab = {
        "title": TAB_WRONG,
        "rows": [
            {"values": [cell(h, bold=True, bg=EVAL_NAVY, fg=WHITE, size=9, box=True)
                        for h in ["中項目", "評価基準", "評価点"]]},
            {"values": [cell(formula=wrong_query, size=9)]},
        ],
        "col_widths": [160, 640, 60], "frozen": 1, "col_count": 3,
        "extra_rows": 230,
    }
    expectations = {
        "total": data["agg"]["合計"], "ba": data["ba"],
        "blocks": data["block_counts"], "agg": data["agg"],
    }
    return [summary_tab] + detail_tabs + [wrong_tab], expectations


# ---------------------------------------------------------------- Sheets API
def services():
    from googleapiclient.discovery import build
    creds = get_creds()
    return build("sheets", "v4", credentials=creds), build("drive", "v3", credentials=creds)


def ensure_spreadsheet(sheets, drive, manifest, key: str, title: str, tab_specs) -> str:
    store = manifest.setdefault("spreadsheets", {})
    entry = store.get(key)
    if entry and entry.get("id"):
        sid = entry["id"]
        meta = sheets.spreadsheets().get(spreadsheetId=sid, fields="sheets.properties").execute()
        existing = {s["properties"]["sheetId"] for s in meta["sheets"]}
    else:
        body = {
            "properties": {"title": title, "locale": "ja_JP", "timeZone": "Asia/Tokyo"},
            "sheets": [{"properties": {"sheetId": SHEET_IDS[t["title"]], "title": t["title"]}}
                       for t in tab_specs],
        }
        created = sheets.spreadsheets().create(body=body).execute()
        sid = created["spreadsheetId"]
        existing = {SHEET_IDS[t["title"]] for t in tab_specs}
        store[key] = {"id": sid, "url": created["spreadsheetUrl"], "title": title}
        save_manifest(manifest)
        # 共有設定は自動付与しない（`share` コマンドで明示的に行う）
        try_move(drive, sid, quiet=True)
    # 欠けているタブを補充
    add_reqs = [{"addSheet": {"properties": {"sheetId": SHEET_IDS[t["title"]], "title": t["title"]}}}
                for t in tab_specs if SHEET_IDS[t["title"]] not in existing]
    if add_reqs:
        sheets.spreadsheets().batchUpdate(spreadsheetId=sid, body={"requests": add_reqs}).execute()
    return sid


def tab_write_requests(spec) -> list[dict]:
    sheet_id = SHEET_IDS[spec["title"]]
    rows = spec["rows"]
    n_rows = len(rows) + spec.get("extra_rows", 4)
    n_cols = spec.get("col_count") or max(len(r["values"]) for r in rows)
    reqs = [
        {"unmergeCells": {"range": {"sheetId": sheet_id}}},
        {"updateCells": {"range": {"sheetId": sheet_id},
                         "fields": "userEnteredValue,userEnteredFormat,dataValidation,note"}},
        {"updateSheetProperties": {
            "properties": {"sheetId": sheet_id, "title": spec["title"],
                           "gridProperties": {"rowCount": n_rows, "columnCount": n_cols,
                                              "frozenRowCount": spec.get("frozen", 0),
                                              "hideGridlines": spec.get("hide_gridlines", True)},
                           "tabColor": TAB_COLORS.get(spec["title"])},
            "fields": ("title,gridProperties(rowCount,columnCount,frozenRowCount,"
                       "hideGridlines),tabColor")}},
        {"updateCells": {"start": {"sheetId": sheet_id, "rowIndex": 0, "columnIndex": 0},
                         "rows": rows, "fields": "userEnteredValue,userEnteredFormat"}},
    ]
    for r0, r1, c0, c1 in spec.get("merges", []):
        reqs.append({"mergeCells": {"mergeType": "MERGE_ALL",
                                    "range": {"sheetId": sheet_id, "startRowIndex": r0, "endRowIndex": r1,
                                              "startColumnIndex": c0, "endColumnIndex": c1}}})
    for i, width in enumerate(spec.get("col_widths", [])):
        reqs.append({"updateDimensionProperties": {
            "range": {"sheetId": sheet_id, "dimension": "COLUMNS", "startIndex": i, "endIndex": i + 1},
            "properties": {"pixelSize": width}, "fields": "pixelSize"}})
    if spec.get("checkbox"):
        r0, r1, c0, c1 = spec["checkbox"]
        reqs.append({"setDataValidation": {
            "range": {"sheetId": sheet_id, "startRowIndex": r0, "endRowIndex": r1,
                      "startColumnIndex": c0, "endColumnIndex": c1},
            "rule": {"condition": {"type": "BOOLEAN"}, "strict": True, "showCustomUi": True}}})
    return reqs


def write_tabs(sheets, sid: str, tab_specs, tab_order: list[str]) -> None:
    reqs = []
    for spec in tab_specs:
        reqs += tab_write_requests(spec)
    for index, title in enumerate(tab_order):
        reqs.append({"updateSheetProperties": {
            "properties": {"sheetId": SHEET_IDS[title], "index": index}, "fields": "index"}})
    sheets.spreadsheets().batchUpdate(spreadsheetId=sid, body={"requests": reqs}).execute()


def set_anyone_reader(drive, fid: str) -> str:
    """1 ファイルの「リンクを知っている全員」権限を閲覧者(reader)に揃える。

    末端ファイル（Doc）に直接付与する前提。フォルダに付けると drive.file スコープ外の
    子ファイルへカスケードして 403 になるため、フォルダには使わない。"""
    perms = drive.permissions().list(
        fileId=fid, fields="permissions(id,type,role)"
    ).execute().get("permissions", [])
    anyone = next((p for p in perms if p["type"] == "anyone"), None)
    if anyone is None:
        drive.permissions().create(fileId=fid, body={"type": "anyone", "role": "reader"}).execute()
        return "created"
    if anyone["role"] != "reader":
        drive.permissions().update(
            fileId=fid, permissionId=anyone["id"], body={"role": "reader"}
        ).execute()
        return "downgraded"
    return "ok"


def secure_docs(drive, manifest) -> None:
    """Docs を「雛形シートの外・各 Doc = リンク共有 閲覧者」に是正する（受講生編集不可・匿名）。

    雛形シートフォルダ（チーム共有 + 全員 writer）配下に置くと anyone:writer が継承され、
    受講生がチケット Doc を編集できてしまう（My Drive の継承は親より弱くできない）。よって
    ① トップフォルダを雛形シートの外（My Drive 直下）へ退避し ② 各 Doc に直接 anyone:reader を
    付与する（フォルダ単位はスコープ外の子ファイルへカスケードして 403 になるため使わない）。
    受講生は「リンクを知っている全員: 閲覧者」でアクセスするため、同時閲覧しても互いに匿名
    （Anonymous Animal）で表示され、身元・アイコンは見えない（Google の公開リンク仕様）。"""
    from googleapiclient.errors import HttpError

    top = manifest["folders"]["top"]
    parents = drive.files().get(fileId=top, fields="parents").execute().get("parents", [])
    if HINAGATA_FOLDER_ID in parents:
        drive.files().update(
            fileId=top, addParents="root", removeParents=HINAGATA_FOLDER_ID, fields="id"
        ).execute()
        print("  ✓ Docs トップフォルダを雛形シートの外（My Drive 直下）へ移動（writer 継承を解消）")
    else:
        print("  = Docs トップフォルダは既に雛形シートの外")

    doc_ids = []
    for e in manifest["tickets"].values():
        if e.get("docId"):
            doc_ids.append(e["docId"])
        for h in e.get("history", []):     # supersede で凍結された旧 Doc も閲覧者に揃える
            if h.get("docId"):
                doc_ids.append(h["docId"])
    stats = {"created": 0, "downgraded": 0, "ok": 0}
    errors = []
    for fid in doc_ids:
        try:
            stats[set_anyone_reader(drive, fid)] += 1
        except HttpError as ex:
            errors.append(f"{fid}: {ex.status_code}")
    print(f"  ✓ Docs {len(doc_ids)} 件を閲覧者に付与"
          f"（新規 {stats['created']} / writer→reader 修正 {stats['downgraded']} / 既に閲覧者 {stats['ok']}）")
    if errors:
        print(f"  ⚠ 失敗 {len(errors)} 件: " + " / ".join(errors))

    sample = doc_ids[0] if doc_ids else None
    if sample:
        sp = drive.permissions().list(
            fileId=sample, fields="permissions(type,role)"
        ).execute().get("permissions", [])
        eff = next((p["role"] for p in sp if p["type"] == "anyone"), "なし")
        mark = "✓" if eff == "reader" else "⚠"
        print(f"  {mark} サンプル Doc の anyone ロール = {eff}"
              + ("（受講生は閲覧のみ・匿名）" if eff == "reader" else ""))


def try_move(drive, file_id: str, quiet=False) -> bool:
    """雛形シートフォルダ直下へ移動を試みる（drive.file スコープでは対象フォルダが見えず失敗しうる）。"""
    from googleapiclient.errors import HttpError
    try:
        parents = drive.files().get(fileId=file_id, fields="parents").execute().get("parents", [])
        if HINAGATA_FOLDER_ID in parents:
            return True
        drive.files().update(fileId=file_id, addParents=HINAGATA_FOLDER_ID,
                             removeParents=",".join(parents), fields="id,parents").execute()
        return True
    except HttpError as e:
        if not quiet:
            print(f"  ⚠ 雛形シートフォルダへの移動不可（{e.status_code}）。ブラウザで手動ドラッグしてください。")
        return False


# ---------------------------------------------------------------- コマンド
def cmd_build(args) -> None:
    manifest = load_manifest()
    sheets, drive = services()
    targets = ["requirement", "evaluation"] if args.target == "all" else [args.target]
    now = datetime.now(timezone.utc).astimezone().isoformat(timespec="seconds")
    for key in targets:
        if key == "requirement":
            specs = build_requirement_tabs(manifest)
            title, order = REQ_TITLE, [TAB_REQ_GAIYOU, TAB_REQ_TICKETS]
        else:
            specs, _ = build_evaluation_tabs()
            title, order = EVAL_TITLE, [TAB_SUMMARY, TAB_STORY, TAB_BUG, TAB_TASK, TAB_CROSS, TAB_WRONG]
        sid = ensure_spreadsheet(sheets, drive, manifest, key, title, specs)
        write_tabs(sheets, sid, specs, order)
        manifest["spreadsheets"][key]["builtAt"] = now
        save_manifest(manifest)
        print(f"✓ {title} を再生成: {manifest['spreadsheets'][key]['url']}")
    if "evaluation" in targets:
        verify_evaluation(sheets, manifest)


def read_values(sheets, sid: str, rng: str):
    res = sheets.spreadsheets().values().get(
        spreadsheetId=sid, range=rng, valueRenderOption="UNFORMATTED_VALUE").execute()
    return res.get("values", [])


def verify_evaluation(sheets, manifest) -> None:
    """総合評価タブの計算値と md 集計の一致を検算。"""
    _, expectations = build_evaluation_tabs()
    entry = manifest.get("spreadsheets", {}).get("evaluation")
    if not entry:
        sys.exit("評価シートが未 build です")
    values = read_values(sheets, entry["id"], f"'{TAB_SUMMARY}'!A1:D120")
    table = {str(r[0]).strip(): r for r in values if r and str(r[0]).strip()}
    problems = []

    def chk(label, exp_points):
        row = table.get(label)
        if not row or len(row) < 2 or not isinstance(row[1], (int, float)):
            problems.append(f"「{label}」行がスプシに見つからない")
            return
        if float(row[1]) != float(exp_points):
            problems.append(f"{label}: スプシ配点 {row[1]} ≠ md {exp_points}")

    chk("総合", expectations["total"][1])
    for label in ["Story（Basic）", "Story（Advance）", "Bug（Basic）", "Bug（Advance）",
                  "Task（Basic）", "Task（Advance）"]:
        chk(label, expectations["agg"][f"チケット要件 {label}"][1])
    chk("Basic 計", expectations["ba"]["Basic"][1])
    chk("Advance 計", expectations["ba"]["Advance"][1])
    if problems:
        sys.exit("NG（スプシ ⇔ md 検算）:\n  " + "\n  ".join(problems))
    print(f"  ✓ スプシ検算 OK: 総合 {expectations['total'][1]:g} 点 / "
          f"Basic {expectations['ba']['Basic'][1]:g} / Advance {expectations['ba']['Advance'][1]:g}")


# チケット一覧タブの列位置（A=スペーサー / B=ID / C=タイトル）
TICKETS_ID_COL, TICKETS_TITLE_COL = 1, 2


def iter_ticket_rows(values):
    """(行番号 1-indexed, チケットID, タイトルセルの現式) を列挙。"""
    for i, row in enumerate(values, start=1):
        if len(row) <= TICKETS_ID_COL:
            continue
        ticket_id = str(row[TICKETS_ID_COL]).strip()
        if not re.fullmatch(r"[SBT]-[AB]-\d+", ticket_id):
            continue
        current = str(row[TICKETS_TITLE_COL]) if len(row) > TICKETS_TITLE_COL else ""
        yield i, ticket_id, current


def cmd_links_sync(args) -> None:
    manifest = load_manifest()
    entry = manifest.get("spreadsheets", {}).get("requirement")
    if not entry:
        sys.exit("要件シートが未 build です（build requirement を先に）")
    sheets, _ = services()
    sid = entry["id"]
    res = sheets.spreadsheets().values().get(
        spreadsheetId=sid, range=f"'{TAB_REQ_TICKETS}'!A1:C300",
        valueRenderOption="FORMULA").execute()
    updates, seen = [], set()
    for i, ticket_id, current in iter_ticket_rows(res.get("values", [])):
        seen.add(ticket_id)
        t_entry = manifest["tickets"].get(ticket_id)
        if not t_entry or not t_entry.get("docUrl"):
            print(f"  ⚠ {ticket_id}: manifest に Doc が無い")
            continue
        want = hyperlink_formula(t_entry, ticket_id)
        if current != want:
            updates.append({"range": f"'{TAB_REQ_TICKETS}'!C{i}", "values": [[want]]})
            print(f"  ✓ {ticket_id}: タイトルリンクを差し替え")
    missing = sorted(set(manifest["tickets"]) - seen)
    if missing:
        print(f"  ⚠ スプシに行が無いチケット（行追加は build requirement で）: {', '.join(missing)}")
    if updates:
        sheets.spreadsheets().values().batchUpdate(
            spreadsheetId=sid,
            body={"valueInputOption": "USER_ENTERED", "data": updates}).execute()
        print(f"完了: {len(updates)} 件のリンクを更新")
    else:
        print("完了: 差分なし（全リンク一致）")


def cmd_verify(args) -> None:
    manifest = load_manifest()
    sheets, _ = services()
    verify_evaluation(sheets, manifest)
    # 要件側: タイトルリンクの一致（links-sync のドライラン相当）
    entry = manifest.get("spreadsheets", {}).get("requirement")
    if entry:
        res = sheets.spreadsheets().values().get(
            spreadsheetId=entry["id"], range=f"'{TAB_REQ_TICKETS}'!A1:C300",
            valueRenderOption="FORMULA").execute()
        mismatch = []
        for i, ticket_id, current in iter_ticket_rows(res.get("values", [])):
            t = manifest["tickets"].get(ticket_id)
            if t and t.get("docUrl") and current != hyperlink_formula(t, ticket_id):
                mismatch.append(ticket_id)
        if mismatch:
            sys.exit(f"NG: タイトルリンク不一致 {len(mismatch)} 件（links-sync を実行）: {', '.join(mismatch)}")
        print("  ✓ チケット一覧のタイトルリンク 全一致")


def cmd_share(args) -> None:
    """マスタスプシ 2 枚にリンク共有を付与する（明示実行専用。build は共有設定を触らない）。

    既存コースのマスタは「リンクを知っている全員: 編集者」だが、付与するロールは
    ユーザーが --role で明示的に選ぶ。"""
    manifest = load_manifest()
    _, drive = services()
    for key, label in [("requirement", "要件シート"), ("evaluation", "評価シート")]:
        entry = manifest.get("spreadsheets", {}).get(key)
        if not entry:
            print(f"  - {label}: 未 build のためスキップ")
            continue
        drive.permissions().create(
            fileId=entry["id"], body={"type": "anyone", "role": args.role}).execute()
        print(f"  ✓ {label}: リンクを知っている全員 = {args.role}")


def cmd_place(args) -> None:
    """マスタスプシ 2 枚のみを雛形シート直下へ移動する。

    Docs トップフォルダは雛形シートに入れない（入れると anyone:writer を継承して受講生が
    編集可能になるため）。Docs 側の配置・共有は `secure-docs` が担当する。"""
    manifest = load_manifest()
    _, drive = services()
    ok = True
    for key, label in [("requirement", "要件シート"), ("evaluation", "評価シート")]:
        entry = manifest.get("spreadsheets", {}).get(key)
        if not entry:
            continue
        moved = try_move(drive, entry["id"])
        print(f"  {'✓' if moved else '✗'} {label}: {'雛形シート直下' if moved else '要手動ドラッグ'}")
        ok = ok and moved
    if not ok:
        print("→ 手動の場合: Drive で対象を選択し、雛形シートフォルダ"
              f"(https://drive.google.com/drive/folders/{HINAGATA_FOLDER_ID}) へドラッグ（ID・リンクは不変）")
    print("  ─ Docs は雛形シートに入れない（受講生編集可を避けるため）。Docs の配置・共有是正は `secure-docs`")


def cmd_secure_docs(args) -> None:
    manifest = load_manifest()
    _, drive = services()
    secure_docs(drive, manifest)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__,
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest="command", required=True)
    p_build = sub.add_parser("build", help="マスタスプシを生成 / 全面再生成")
    p_build.add_argument("target", choices=["requirement", "evaluation", "all"])
    sub.add_parser("links-sync", help="チケット一覧のタイトル列リンクを manifest と同期")
    sub.add_parser("verify", help="スプシ集計 ⇔ md 集計の検算 + リンク一致確認")
    sub.add_parser("place", help="マスタスプシ 2 枚を雛形シートフォルダへ移動")
    sub.add_parser("secure-docs", help="Docs を雛形シートの外へ出し「全員: 閲覧者」に是正（受講生編集不可・匿名）")
    p_share = sub.add_parser("share", help="スプシ 2 枚へのリンク共有付与（明示実行専用）")
    p_share.add_argument("--role", choices=["reader", "writer"], required=True,
                         help="リンクを知っている全員に付与するロール")
    args = parser.parse_args()
    {"build": cmd_build, "links-sync": cmd_links_sync, "verify": cmd_verify,
     "place": cmd_place, "secure-docs": cmd_secure_docs, "share": cmd_share}[args.command](args)


if __name__ == "__main__":
    main()
