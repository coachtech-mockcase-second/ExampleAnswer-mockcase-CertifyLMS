---
name: worktree-spawn
description: 並列実装用に git worktree を作成し、その worktree で別 Claude Code セッションを立ち上げる手順を提示する。$ARGUMENTS に Feature 名 or 複数 Feature をカンマ区切りで渡す。並列実装はユーザーが各 worktree で個別に Claude セッションを起動して spec-generate / feature-implement を直列実行することで実現
---

# worktree-spawn

並列実装用の **git worktree 作成** と、各 worktree で **別 Claude Code セッション** を立ち上げる手順を提示するスキル。

## なぜこの Skill か

複数 Feature を並列で実装したい場合、subagent 経由ではなく **複数 Claude Code セッション**（各 worktree に1セッション）を立ち上げる方が:

- **真の並列**（各セッションが独立コンテキスト）
- 主セッションのコンテキストを圧迫しない
- Anthropic 公式推奨パターン（`claude --worktree`）と一致

このスキルは worktree 作成と起動手順の提示までを担当し、**各 worktree でユーザーが個別に Claude セッションを起動** して `spec-generate` / `feature-implement` Skill を使う運用。

## 入力

`$ARGUMENTS`: Feature 名（複数の場合カンマ区切り）。例:

- `enrollment` — 1 Feature
- `certification-management,content-management,enrollment,learning` — 4 Feature 並列

無ければユーザーに確認する。

## 処理フロー

### 1. 前提確認

- 現在のブランチを確認（`git branch --show-current`）
- 未コミットの変更がないか確認（`git status`）
- `claude` コマンドのバージョンが worktree 対応か確認（v2.1.49+）

### 2. 各 Feature 用に worktree 作成

`claude --worktree <feature>` で各 Feature の worktree を生成:

```bash
claude --worktree enrollment
claude --worktree learning
# ... 各 Feature ごと
```

これにより `.claude/worktrees/<feature>/` に独立した worktree が作成され、`worktree-<feature>` ブランチで `origin/HEAD`（または `.claude/settings.json` の `worktree.baseRef` 設定により現在のブランチ）から枝分かれする。

### 3. `.worktreeinclude` で必要ファイルをコピー

worktree 間で `.env` 等の git 管理外ファイルを共有するため、`.worktreeinclude` に列挙:

```
.env
.env.local
database/database.sqlite
```

### 4. 各 worktree で Claude セッション起動の手順をユーザーに提示

```
各 worktree でターミナルを開き、以下を実行してください:

  cd .claude/worktrees/<feature>
  claude

セッション起動後、以下のいずれかを実行:
  - /spec-generate <feature>       — spec 3点セット生成
  - /feature-implement <feature>   — Laravel 実装（次の未完了 Step）

各セッションは独立コンテキストで並列動作します。
```

### 5. 完了後のマージ手順を提示

```
各 worktree の作業完了後:
  1. 各 worktree でテスト通過 + Pint 整形を確認
  2. メインブランチに切り替え: git checkout basic
  3. 各 worktree をマージ: git merge worktree-<feature>
  4. routes/web.php のマージ衝突は標準 Git 手動解決
  5. 全 Feature マージ後に統合テスト: php artisan test
  6. worktree クリーンアップ: git worktree remove .claude/worktrees/<feature>
```

## 並列度の指針

- **4-6 worktree 同時** が業界実用上限（端末切替コスト・レビュー律速）
- **3-5 がスイートスポット**

## CLAUDE.md「実装プラン」との連動

並列実装する Feature の選定は CLAUDE.md「実装プラン」の Feature 一覧と依存関係を参照:

- 後続の前提（`auth` / `user-management`）は **直列で先に完了** させる
- 独立 Feature は並列起動可
- 集計依存 Feature（`notification` / `dashboard` 等）は後半 or 直列

## 制約

- **基盤 Feature 完了前に依存 Feature を並列起動しない**（競合回避）
- 各 worktree は **独立 SQLite DB**（`database/database_{name}.sqlite`）を使う
- `composer.json` / `package.json` / `bootstrap/providers.php` は Wave 0b で確定、worktree では編集禁止
- worktree 残骸は作業完了後に必ず棚卸し（`git worktree prune`）

## 完了報告

- 作成した worktree のパス一覧
- 各 worktree で起動するコマンドの手順
- マージ手順

## 注意

このスキルは **手順提示** が主目的。実際の worktree 内での作業は user が各セッションで進める。主セッションは並列処理を「指示」するだけで、並列実行自体は user の操作（複数ターミナルでセッション起動）で実現される。
