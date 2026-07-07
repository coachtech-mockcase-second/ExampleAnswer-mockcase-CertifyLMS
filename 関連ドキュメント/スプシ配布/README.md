# スプシ配布 — 30%版チケット Docs + マスタスプシ同期

30%版要件シートのスプシ運用（**issue #67**）のための構築側メタディレクトリ。**AssignedProject にはコピーしない**。

リポジトリの md を SSoT として、Drive 上の配布物を生成・更新・検証・版管理する:

- `関連ドキュメント/要件シート_詳細度30%/{Story|Bug|Task}/*.md` → **チケット Doc 41 件**（`sync_docs.py`）
- `関連ドキュメント/要件シート_詳細度30%/概要.md` + manifest → **模擬案件_CertifyLMS_要件シート（詳細度30%）**（`build_sheets.py`）
- `関連ドキュメント/評価シート.md` → **模擬案件_CertifyLMS_評価シート**（`build_sheets.py`）

**Doc・スプシは派生ビューであり Google 上での手編集は禁止**（軽微修正も md を直して同期する）。修正・版管理の運用は **`運用ガイド.md`**、設計の全体像は issue #67 を参照。

## 構成

| ファイル | 役割 |
|---|---|
| `README.md` | 本ファイル（セットアップ + コマンド一覧） |
| `運用ガイド.md` | 軽微=push / 実質=supersede の判断基準・手順チートシート・検証・トラブルシュート |
| `manifest.json` | Doc 41 件 + マスタスプシ 2 枚の ID / URL / version / ハッシュ / 差し替え履歴（各コマンドが生成・更新） |
| `scripts/sync_docs.py` | チケット Doc 同期ツール（init / push / verify / supersede / links） |
| `scripts/build_sheets.py` | マスタスプシ生成ツール（build / links-sync / verify / place） |
| `scripts/credentials.json` `scripts/token.json` | 認証情報（**コミット禁止**、.gitignore 済み） |

## 初回セットアップ（約 10 分）

1. [GCP コンソール](https://console.cloud.google.com/) でプロジェクトを作成（任意名、例: `certify-docs-sync`）
2. 「API とサービス」→「ライブラリ」→ **Google Drive API** と **Google Sheets API** を有効化
3. 「API とサービス」→「OAuth 同意画面」→ User Type **External** で作成 → **テストユーザーに自分の Google アカウントを追加**
   - 公開ステータスが「テスト」のままだとリフレッシュトークンが **7 日で失効** する（失効時は `token.json` を消して再認証）。恒久運用には「本番」へ変更を推奨（`drive.file` は非機密スコープのため審査不要）
4. 「API とサービス」→「認証情報」→「認証情報を作成」→「OAuth クライアント ID」→ 種類 **デスクトップアプリ** → 作成後 JSON をダウンロードし `scripts/credentials.json` として配置
5. 依存ライブラリ（venv）:

   ```bash
   cd 関連ドキュメント/スプシ配布/scripts
   python3 -m venv .venv
   .venv/bin/pip install google-api-python-client google-auth-oauthlib
   ```

6. 初回コマンド実行時にブラウザが開くので Google アカウントで承認 → `scripts/token.json` が生成され、以後は自動

権限スコープは `drive.file`（**このツールが作成したファイルのみ**操作可）に限定している。

## コマンド

`関連ドキュメント/スプシ配布/scripts/` で `.venv/bin/python <script> <command>`:

### sync_docs.py（チケット Doc）

| コマンド | 用途 |
|---|---|
| `init` | Drive にフォルダ構成（トップ + Story / Bug / Task / _archive）を作成し、トップに「リンクを知っている全員: 閲覧者」を設定。manifest 初期化 |
| `push S-B-02 ...` / `push --all` | md から Doc を生成 / 既存 Doc の内容置換（**URL 不変**）。**軽微修正はこちら**。md 無変更のチケットは自動スキップ |
| `verify` | ① md ハッシュ検証（ローカルのみ・再同期忘れ検知） |
| `verify --remote` | ① + ② Doc の手編集検知（modifiedTime 比較） |
| `verify --content` | ① + ② + ③ Doc を md で export し正規化して内容突合 |
| `supersede S-B-02 --reason "..."` | **実質変更**: 新 Doc 作成 + 旧 Doc を `_archive/` へ無改変で凍結移動 + manifest の version / history 更新 |
| `links --out チケット一覧_リンク.tsv` | タイトル列 HYPERLINK 式の一覧 TSV（手貼り用の予備。通常は `build_sheets.py links-sync` を使う） |

### build_sheets.py（マスタスプシ）

| コマンド | 用途 |
|---|---|
| `build requirement` | 要件シート（シート1 概要 / シート2 チケット一覧）を生成 / 全面再生成（URL 不変・md 検算つき） |
| `build evaluation` | 評価シート（総合評価 / Story / Bug / Task / 横断品質 / 誤答リスト）を生成 / 全面再生成 + 検算 |
| `build all` | 上記 2 枚とも |
| `links-sync` | チケット一覧のタイトル列 HYPERLINK を manifest と突合して差分だけ更新（**supersede 後に必ず実行**） |
| `verify` | 評価スプシの集計 ⇔ 評価シート.md の検算 + タイトルリンクの一致確認 |
| `place` | Docs トップフォルダ + マスタスプシ 2 枚を雛形シートフォルダ直下へ移動（不可なら手動ドラッグ案内） |

## 運用ルール（要点）

- **Doc・スプシを Google 上で直接編集しない**。すべての変更は md → push / supersede / build で反映（verify が手編集・ズレを検知する）
- **軽微**（誤字・表記ゆれ・書式など、仕様・採点に影響しない）= `push`（過去に開始した生徒にも反映される）/ **実質**（要件・振る舞い・スコープ・インターフェース・依存・工数）= `supersede`（過去生徒は旧版のまま）。**迷ったら supersede**
- `supersede` 後は **`build_sheets.py links-sync`** でマスタスプシのタイトル列リンクを差し替える
- 30% md の実質変更時は、100%版・評価シートとの派生整合（CLAUDE.md 修正波及表）を従来どおり同一作業単位で行い、評価シート.md が動いたら `build evaluation` も実行する
- Claude Code では **Skill `/distribution-sync [ID,...]`** が「ドリフト検査 → 軽微 / 実質の判定提案 → 実行 → links-sync / build → 検証」を一括で行う（引数省略で変更検出スイープ）

詳細な判断基準・手順チートシート・トラブルシュートは `運用ガイド.md` に集約。
