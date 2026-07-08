---
name: assigned-project-sync
description: メタリポの 提供プロジェクト/ を受講生配布用テンプレリポ AssignedProject-mockcase-CertifyLMS(main ブランチ)へミラー同期する運用 Skill。提供PJ のコード・Seeder・README・同梱テスト・Blade に変更を入れたら、この Skill で公開テンプレへ反映しない限り受講生・採点者には届かない。「AssignedProject へ同期」「テンプレに反映」「配布リポを更新」「提供PJ の変更を公開して」と言われた時はもちろん、Step 4 のクラスタ完了後・採点者フィードバック対応・バグ修正などで 提供プロジェクト/ 配下を変更した文脈が見えたら、明示的に skill 名を出されていなくても必ずこの Skill を使うこと。逆に履歴リセットを伴う初回配置はこの Skill の対象外。
---

# assigned-project-sync

メタリポ(本リポ)の `提供プロジェクト/` を SSoT として、受講生が "Use this template" で自リポ生成する公開テンプレ **coachtech-prepared-file/AssignedProject-mockcase-CertifyLMS** へ **ミラー同期** する。

## なぜこの Skill が必要か

- `提供プロジェクト/` への変更(バグ仕込み・Seeder・README・同梱テスト)は **push しただけでは配布物に反映されない**。テンプレは独立リポで自動追従しない
- 同期漏れは「チケットの前提が配布物で成立しない」事故に直結する(例: 同梱テストが存在しない / ダミーデータが無い / README のコマンドが古い)。採点者フィードバック対応(2026-07)で実際にこの漏れが起きた
- `git archive` は `.gitattributes` の export-ignore で欠けるため使えない。**`git ls-files` ベースのミラーが正**(初回配置と同方式)

対象リポ・ブランチ構成が変わった場合は `CLAUDE.md`「リポジトリ・ブランチ」表を正とする(現行: `main` 一本=デフォルト)。

## 手順

### 1. 前提確認

- メタリポの `git status --porcelain -- 提供プロジェクト` がクリーンであること(未コミット変更は同期しない — SSoT はコミット済み状態)。汚れていれば先にコミットするかユーザーに確認
- `gh api repos/coachtech-prepared-file/AssignedProject-mockcase-CertifyLMS/branches --jq '.[] | "\(.name) \(.commit.sha[0:7])"'` で現在の `main` の SHA を取得。最終コミット日時・メッセージは `gh api "repos/.../commits?sha=main&per_page=1" --jq '.[0].commit | "\(.committer.date) \(.message | split("\n")[0])"'` で確認し、どのくらい遅れているかを報告する
- **`main` 以外のブランチが存在したら想定外**(2026-07 に basic/advance を main へ一本化済み)。原因を確認しユーザーに相談してから進む

### 2. clone + ミラー構築

```bash
git clone --quiet git@github.com:coachtech-prepared-file/AssignedProject-mockcase-CertifyLMS.git <scratchpad>/AssignedProject
python3 .claude/skills/assigned-project-sync/scripts/mirror_provided_pj.py --clone <scratchpad>/AssignedProject
```

スクリプトは ls-files 取得 → clone 側 `.git` 以外全削除 → プレフィクスを剥がしてコピー、まで行う(安全弁: 未コミット変更 / ファイル数異常 / `.git` 欠如で中止)。**コミット・push はしない** — 次のレビューゲートを挟むため。

### 3. 差分レビュー(安全ゲート、スキップ禁止)

clone 先で `git add -A` → `git status --short` を集計し、**必ず確認してから**先へ進む:

- **削除(D)は全件レビュー**: テンプレ側にしか無いファイルを消していないか。ミラー方式では「メタ側で消したファイル」だけが D になるのが正常。心当たりのない D が出たらユーザーに提示して判断を仰ぐ
- **追加(A)・変更(M)が今回の変更内容と対応しているか**: 意図した変更(例: Seeder 追加・README 修正)+ 整形差分のみか。非自明な M は `git diff --cached <file>` でスポットチェック(Pint 整形のみ等を確認)
- `docs/` `.claude/` `関連ドキュメント/` `memo/` 由来のファイルが混入していたら **即中止**(構築側メタの漏洩。`提供プロジェクト/` 配下しか対象にならないはずなので、混入 = パス指定ミス)
- **差分が 0 件なら既に同期済み**。空コミットは作らず「同期不要(テンプレは最新)」と報告して終了する

### 4. コミット(受講生視点の中立メッセージ)

テンプレの git 履歴は **受講生に見える**。コミットメッセージで課題設計をネタバレさせない:

- **禁止語彙**: バグ / 仕込み / 引き算 / gut / 採点 / 評価 / 模範解答 / 提供PJ / チケットID(S-B-01 等) / Step 4 / FB対応 等の構築側メタ
- **書き方**: 変更を「開発リポの普通のメンテナンス」として業務語彙で記述する

```
✅ 良い例:
chore: シードデータ拡充・定期実行コマンドの検証テスト追加・環境設定更新・コード整形

❌ 悪い例:
fix(採点FB): T-B-03 を offset chunk にバグ化 + 同梱テスト追加
```

末尾に `Co-Authored-By: Claude <モデル名> <noreply@anthropic.com>` を付ける。

### 5. push(main・force 禁止)

```bash
git push origin HEAD:main
```

- **force-push・履歴リセットはしない**(履歴リセットが必要な事態は Step 6 配置のやり直しであり、この Skill の範囲外としてユーザーに相談)
- 生成済みの受講生リポには反映されない点に注意 — 運用開始後の同期では、影響するチケットがあるか + 周知の要否をユーザーに一言添える

### 6. リモート検証(push しただけで終わらない)

- `main` が push した SHA を指しているか: `gh api .../branches --jq ...`
- **今回の変更の代表ファイル 2〜3 件**を `gh api "repos/.../contents/<path>?ref=main" --jq '.content' | base64 -d | grep <期待パターン>` で実確認(例: 追加した Seeder のアカウント名、README のイメージ名)

### 7. 報告

- 同期コミット SHA / 差分内訳(追加・変更・削除の件数と要点)/ リモート検証結果
- Dependabot 警告が出ても既存依存由来でこの同期とは無関係(報告時にその旨ひと言でよい)

## この Skill でやらないこと

- 履歴リセットを伴う初回配置・再配置(Step 6 の配置手順)
- `docs/` `関連ドキュメント/` `ONBOARDING.md` 等の内容編集(同期は写すだけ。内容の修正はメタリポ側で先に行う)
- 30% 版要件のスプシ / Docs 配布(別ワークフロー)
