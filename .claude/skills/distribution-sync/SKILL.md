---
name: distribution-sync
description: リポジトリ md（30% チケット / 概要.md / 評価シート.md）の修正を配布物（チケット Google Docs + マスタスプシ 2 枚）へ同期する Skill。引数 = チケット ID（カンマ区切り複数可）または省略（変更検出スイープ）。チケットは diff から「軽微 = push（同一 URL 更新）/ 実質 = supersede（新 Doc + 旧版凍結）」を判定・提案し、承認後に同期・スプシリンク差し替え・再生成・検証まで一括実行する。要件/評価シートの修正反映・配布物の同期を依頼されたら本 Skill を使うこと。
---

# Certify LMS 配布物同期 Skill

md（SSoT）の修正を、生徒配布中の Docs / マスタスプシへ**版管理ルールに従って**反映する統合フロー。コマンドは `関連ドキュメント/スプシ配布/scripts/` で `.venv/bin/python` から実行する。

## 入力

`$ARGUMENTS`: チケット ID（カンマ区切り複数可）。**省略時は変更検出スイープ** — git status + `sync_docs.py verify` + manifest ハッシュから、前回同期以降に変更された 30% チケット / 概要.md / 評価シート.md を列挙して対象にする。

## 必須読み込み

1. **`関連ドキュメント/スプシ配布/運用ガイド.md`** — 軽微 / 実質の判断基準・LMS 複製の特性（判定根拠はすべてここ）。
2. `関連ドキュメント/スプシ配布/manifest.json` の該当エントリ（`mdSha256` / `gitCommit` / `version` / `spreadsheets`）。

## プロセス

0. **ドリフト検査**: `sync_docs.py verify --content` + `build_sheets.py verify` を先に実行。マスタ / Doc の手編集を検知したら、取り込む（md へ反映）か上書きするかをユーザーに確認してから進む。
1. **対象の確定**: 引数 ID（または検出スイープ結果）を「チケット md / 概要.md / 評価シート.md」に仕分けて提示。
2. **チケット md**（各 ID）:
   - diff 取得: `git diff <manifestのgitCommit> -- <30%md>`（コミット不明・dirty なら HEAD 比較で代替）。sha 一致（無変更）はスキップ報告。
   - 判定: **軽微**（誤字・表記ゆれ・書式・ニュアンス = 仕様・採点に影響しない）= `push` / **実質**（要件・振る舞い・スコープ・インターフェース・依存の変更）= `supersede`。**迷ったら supersede**（過去生徒の採点前提を壊さない側）。
   - AskUserQuestion で「判定 + 根拠（diff の要点）+ 生徒への見え方（push = 過去に開始した生徒にも反映 / supersede = 過去生徒は旧版のまま）」を提案し承認を得る。supersede は `--reason` 文もあわせて提案。
   - 実行: `sync_docs.py push <ID>` / `sync_docs.py supersede <ID> --reason "..."` → supersede 時は **`build_sheets.py links-sync`**（マスタスプシのタイトルリンク差し替え）。
3. **概要.md の変更** → `build_sheets.py build requirement`（チケットのタイトル・依存・件数が動いた場合の一覧反映もこれ）。
4. **評価シート.md の変更** → `build_sheets.py build evaluation`（md 内部検算 + スプシ読み戻し検算つき）。
5. **新 Doc が生成されたら `build_sheets.py secure-docs`**: 新規チケットの `push` や `supersede` は**新しい Doc を作る**。新 Doc は既定で公開権限が付かず受講生が閲覧できないため、`secure-docs` で「リンクを知っている全員: 閲覧者」を付与する（既存 Doc は冪等スキップ・受講生編集不可・匿名閲覧を担保）。内容置換だけの `push`（同一 Doc）では不要。
6. **最終検証**: `sync_docs.py verify --content` + `build_sheets.py verify`。

複数対象は判定をまとめて提案し、実行は順番に行う。

## 完了報告

対象ごとに「判定（push / supersede / build）/ URL（supersede は新旧両方）/ 検証結果 / **生徒への反映範囲**（過去に開始した生徒に届くか否か）」を 1 行ずつ。実質変更だった場合は、100%版・評価シートとの派生整合（CLAUDE.md 修正波及表）の要否を最後に注意喚起する。

## 禁止

- 判定の独断実行（必ず提案 → 承認を挟む。純粋な誤字 1 文字でも報告はする）
- Google Docs / スプシ側の直接編集の提案（SSoT は常にリポジトリ md）
- md 未修正のままの `supersede --force`（バージョンだけ進む無意味な差し替え。リハーサル等でユーザーが明示した場合を除く）
