---
name: ticket-doc-sync
description: 30% 版チケット md の修正を配布物（Google Docs + マスタスプシ）へ反映する Skill。チケット ID（例 `S-B-02`、カンマ区切り複数可）を引数に、変更 diff から「軽微 = push（同一 URL 更新）/ 実質 = supersede（新 Doc + 旧版凍結）」を判定・提案し、承認後に同期・リンク差し替え・検証まで一括実行する。30% チケット修正後の Docs / スプシ反映を依頼されたら本 Skill を使うこと。
---

# Certify LMS チケット Doc 同期 Skill

30% md（SSoT）の修正を、生徒配布中の Google Docs とマスタスプシへ**版管理ルールに従って**反映する。

## 入力

`$ARGUMENTS`: チケット ID（1 件 or カンマ区切り複数件）。なければ `git status` / `sync_docs.py verify` から変更済みチケットを特定してユーザーに確認。

## 必須読み込み

1. **`関連ドキュメント/スプシ配布/運用ガイド.md`** — 軽微 / 実質の判断基準・LMS 複製の特性（判定根拠はすべてここ）。
2. `関連ドキュメント/スプシ配布/manifest.json` の該当エントリ（`mdSha256` / `gitCommit` / `version`）。

## プロセス（各 ID）

1. **diff 取得**: `git diff <manifestのgitCommit> -- <30%md>`（コミットが dirty 表記・不明なら `git diff HEAD` + 直近コミットで代替）。md が前回同期から無変更（sha 一致）ならスキップ報告。
2. **判定**: 運用ガイドの基準表に照らして分類する。
   - **軽微** = 誤字・表記ゆれ・書式・ニュアンス（仕様・採点に影響しない）→ `push`
   - **実質** = 要件・振る舞い・スコープ・インターフェース・依存チケット・工数の変更 → `supersede`
   - **迷ったら supersede**（過去に開始した生徒の採点前提を壊さない側）。
3. **提案**: AskUserQuestion で「判定 + 根拠（diff の要点）+ 生徒への見え方（push = 過去生徒にも反映 / supersede = 過去生徒は旧版のまま）」を提示し承認を得る。supersede の場合は `--reason` 文もあわせて提案。
4. **実行**（`関連ドキュメント/スプシ配布/scripts/` で `.venv/bin/python`）:
   - 軽微: `sync_docs.py push <ID>`
   - 実質: `sync_docs.py supersede <ID> --reason "..."` → **`build_sheets.py links-sync`**（マスタスプシのタイトル列リンク差し替え）
5. **検証**: `sync_docs.py verify --content` + （supersede 時）`build_sheets.py verify`。

複数 ID は判定をまとめて提案し、実行は ID ごとに順番に行う。

## 完了報告

各 ID について「判定（push / supersede）/ Doc URL（supersede は新旧両方）/ links-sync の結果 / verify 結果」を 1 行ずつ。実質変更だった場合は、100%版・評価シートとの派生整合（CLAUDE.md 修正波及表）の要否を最後に注意喚起する（評価シート.md が動いたなら `build_sheets.py build evaluation` も案内）。

## 禁止

- 判定の独断実行（必ず提案 → 承認を挟む。純粋な誤字 1 文字でも報告はする）
- Google Docs / スプシ側の直接編集の提案（SSoT は常にリポジトリ md）
- md 未修正のままの `supersede --force`（バージョンだけ進む無意味な差し替え）
