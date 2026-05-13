---
name: feature-implement
description: 1 Feature の Laravel 実装を docs/specs/{name}/tasks.md の次の未完了 Step に従って実装ディレクトリに進める。$ARGUMENTS に Feature 名を渡す。自己完結・直列。並列で複数 Feature を実装したい場合は worktree-spawn Skill で別 Claude セッションを立ち上げて各セッションでこの Skill を使う
---

# feature-implement

`docs/specs/{name}/` の SDD に基づいて、**実装ディレクトリ**（CLAUDE.md「実装プラン」参照、Certify LMS では `模範解答プロジェクト/`）に Laravel コードを実装する自己完結スキル。直列実行。

## 入力

`$ARGUMENTS`: Feature 名（kebab-case）。例: `mock-exam`
無ければユーザーに確認する。

## 必須読み込み

1. `CLAUDE.md` — 「実装プラン」セクション（実装ディレクトリ確認）
2. `docs/specs/{name}/requirements.md` — 受け入れ基準
3. `docs/specs/{name}/design.md` — アーキテクチャ・データモデル
4. `docs/specs/{name}/tasks.md` — タスク順序（**次の未完了 Step を特定**）
5. 依存先 Feature の `docs/specs/{dep}/design.md`（基盤 Feature 等、先に完了済み想定）
6. `.claude/rules/` 配下（paths frontmatter で自動適用）
7. 既存実装パターン（同レイヤーの近いファイル）

## 参考にする既存実装

**主参考: COACHTECH LMS の `steering-execute` Skill**

- `/Users/yotaro/lms/.claude/skills/steering-execute/SKILL.md` — チェックボックス解析 → 次の未完了 Step 特定 → 既存パターン Read → 実装 → テスト → tasks 更新 → 完了報告 の流れ。本 Skill の処理フローはこれをほぼ踏襲

**補助参考: COACHTECH LMS の `backend-test-writer` agent**

- `/Users/yotaro/lms/.claude/agents/backend-test-writer.md` — UseCase（Action）作成と同時にテスト生成する SOP。本 Skill でも Step 4 で Action 実装と同時に Step 6 のテストを書く流れの参考

**補助参考: COACHTECH LMS の既存実装パターン**

- `/Users/yotaro/lms/backend/app/UseCases/` 配下を Grep して、似た規模の Feature（例: ChatMessage 系）の Action 構成と粒度感を確認

## 処理フロー

### 1. 次の未完了 Step を特定

`docs/specs/{name}/tasks.md` のチェックボックスを解析:
- `- [x]` = 完了
- `- [ ]` = 未完了

最初に `- [ ]` を含む Step を実装対象とし、ユーザーに伝えてから着手。

### 2. Step 内タスクを上から順に実装

**実装前**:
- 変更対象既存コードを Read（未読のコードを変更しない）
- 類似既存ファイルをパターン参照（命名・構造・テスト形式）

**実装中**:
- `.claude/rules/` の規約に厳格に従う（paths frontmatter で自動ロード）
- 新規ファイルは既存の同種ファイルを参考に
- Action / Service / Model / Test / Blade すべて同セッションで生成
- PostToolUse hook（Pint）が PHP ファイル整形を自動実行

### 3. テスト実行（バックエンドの実装を含む Step）

```bash
cd {実装ディレクトリ} && php artisan test --filter={Entity}
```

失敗時は修正してから次へ。

### 4. tasks.md 更新

完了タスクを `[ ]` → `[x]`。

### 5. 完了報告

- 完了した Step 番号とタイトル
- 変更したファイル一覧（パス + 行数）
- テスト結果サマリ
- 次の Step の概要

## 各 Step の参照ルール（paths frontmatter で自動適用）

| Step | 主参照 rules | 主作業 |
|---|---|---|
| 1 Migration & Model | `backend-models.md` | ULID, SoftDeletes, fillable, casts, Enum, Factory |
| 2 Policy | `backend-policies.md` | viewAny/view/create/update/delete、ロール別 match |
| 3 HTTP 層 | `backend-http.md` | Controller 薄く / FormRequest / routes/web.php に追記 |
| 4 Action / Service | `backend-usecases.md` `backend-services.md` `backend-exceptions.md` | `{Action}Action.php`（Controller method 名と一致）、DB::transaction、ドメイン例外 |
| 5 Blade | `frontend-blade.md` `frontend-tailwind.md` | layouts/app 継承、@csrf、@can、コンポーネント、Tailwind utility |
| 6 テスト | `backend-tests.md` | RefreshDatabase + actingAs、各ロール認可分岐、ファクトリ |
| 7 動作確認 | — | Pint 整形 + テスト全通過 + ブラウザ確認 |

## 制約

- **実装ディレクトリ配下のみ編集**（`docs/` は読み取りのみ）
- 1 Skill 実行 = 1 Feature × 1 Step（複数 Step を勝手に進めない）
- 既存テストを壊さない
- 並列実行したい場合は `worktree-spawn` Skill 経由で別 Claude セッションを立ち上げる

## 完了基準

- 該当 Step の全タスクが `[x]`
- テスト追加 + 全通過
- Pint 整形完了
