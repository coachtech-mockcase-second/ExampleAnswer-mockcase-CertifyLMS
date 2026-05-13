---
name: spec-generate
description: 1 Feature の docs/specs/{name}/{requirements,design,tasks}.md の3点セットを生成する。$ARGUMENTS に Feature 名（例 mock-exam）を渡す。自己完結・直列。並列で複数 Feature を生成したい場合は worktree-spawn Skill で別 Claude セッションを立ち上げて各セッションでこの Skill を使う
---

# spec-generate

1 Feature の **完成形 SDD（spec 3点セット）** を自己完結で生成するスキル。直列実行。

## 入力

`$ARGUMENTS`: Feature 名（kebab-case）。例: `mock-exam`, `enrollment`, `auth`
無ければユーザーに確認する。

## 必須読み込み

実行前に Read:

1. `CLAUDE.md` — 「実装プラン」セクション（Feature 一覧・依存関係を確認）
2. `docs/steering/product.md` — 該当 Feature の説明 + 関連 UXフロー + stateDiagram
3. `docs/steering/tech.md` — Clean Architecture / 命名規則 / Action命名 / PR規約
4. `docs/steering/structure.md` — ディレクトリ / 命名規則 / specs/ 作成ルール
5. `.claude/rules/` 配下のルール（paths frontmatter で自動ロード）
6. 依存先 Feature の `docs/specs/{dep}/design.md`（あれば）

## 参考にする既存実装

**実装パターン**（design.md の中身）と **SDD ドキュメント構造**（spec の書き方）は別軸で参考にする。

### 📦 実装パターン: COACHTECH LMS

`/Users/yotaro/lms/backend/` — 本番運用中の Laravel LMS。Certify と同じ Clean Architecture（軽量版）+ Eloquent + Action/Service 構成、ディレクトリ・命名規則も近い。

**design.md 生成前に毎回調査**:

| ステップ | コマンド例 |
|---|---|
| Model 探索 | `ls /Users/yotaro/lms/backend/app/Models/` |
| 関連コード横断検索 | `grep -rli "{キーワード}" /Users/yotaro/lms/backend/app/` |
| Migration 探索 | `find /Users/yotaro/lms/backend/database/migrations -name "*{keyword}*"` |

- **対応実装あり** → Model / Controller / Action / Migration を Read し、観察パターンを design.md 先頭「参考実装」セクションに明記
- **対応実装なし** → LMS 業界標準（Laravel コミュニティ標準 + Moodle / Canvas 等の慣習）+ 周辺の類推可能な実装で補完し、「業界標準に準拠」と明記

> Certify は **ULID 採用・SoftDeletes 標準・教育PJスコープ** という差異あり。COACHTECH の設計をそのままコピーせず、`structure.md` / `tech.md` / `.claude/rules/` に翻訳する。

### 📝 SDD ドキュメント構造

| 参考 | 何を学ぶか |
|---|---|
| **iField LMS** (`/Users/yotaro/ifield-lms/.kiro/specs/contents-and-quizzes/`) | Kiro 流 5ファイル構成 + 各ファイルの構造・粒度・密度（大型 Feature で要件 10+ / 設計 200+ 行 / tasks 50+ チェックボックス）|
| **COACHTECH LMS の steering Skill** (`/Users/yotaro/lms/.claude/skills/steering/SKILL.md`) | `1-requirements.md` / `2-design.md` / `3-tasklist.md` の段階構造 + タスク粒度（1 タスク = 1 コミット）|

## 生成する3ドキュメント

### 1. `docs/specs/{name}/requirements.md`

EARS形式（"The system shall ...", "When ...", "If ...", "While ..."）の受け入れ基準。

```markdown
# {Feature 名} 要件定義

## 概要
（Feature の役割、product.md の該当箇所のサマリ、3-5行）

## ロールごとのストーリー
- 受講生（student）: ...
- コーチ（coach）: ...
- 管理者（admin）: ...

## 受け入れ基準（EARS形式）

### 機能要件
- **REQ-{name}-001**: The system shall ...
- **REQ-{name}-002**: When ..., the system shall ...
- **REQ-{name}-003**: If ..., the system shall ...

### 非機能要件
- **NFR-{name}-001**: ...

## スコープ外
（明示的に対象外とするもの）

## 関連 Feature
（依存先・依存元の Feature）
```

要件 ID は `REQ-{name}-{NNN}` 形式（design / tasks からトレース可能）。

### 2. `docs/specs/{name}/design.md`

**🔴 生成前提**: 「参考にする既存実装 → 調査手順」で COACHTECH LMS を調査済みであること。観察したパターンは **設計内容そのものに織り込む**（design.md 内に「参考実装」セクションは設けない、調査結果のサマリは完了報告で伝える）。

```markdown
# {Feature 名} 設計

## アーキテクチャ概要
（Mermaid sequenceDiagram or flowchart）

## データモデル
- Eloquent モデル一覧（structure.md 準拠、ULID + SoftDeletes）
- リレーション図（Mermaid erDiagram）
- 主要カラム + Enum

## 状態遷移
（該当する場合のみ。stateDiagram-v2 単行ラベル、`:` をラベル内で使わない）

## コンポーネント

### Controller
- {Entity}Controller — メソッド一覧（index/show/store/update/destroy + カスタム）

### Action（UseCase）
- IndexAction / ShowAction / StoreAction / UpdateAction / DestroyAction / {Custom}Action
- 各 Action の責務と入出力（**Controller method 名と一致**）

### Service
- {Feature}Service — 共有計算ロジック（あれば）

### Policy
- {Entity}Policy — viewAny / view / create / update / delete + カスタム

### FormRequest
- StoreRequest / UpdateRequest — バリデーション・認可

### Resource（API のみ）
- {Entity}Resource

## Blade ビュー
- 画面一覧（index / show / form / etc.）
- 主要コンポーネント

## エラーハンドリング
- 想定例外（app/Exceptions/{Domain}/ 配下）
- 状態整合性違反時の例外

## 関連要件
（REQ-{name}-001 → 実装ポイント のマッピング）
```

### 3. `docs/specs/{name}/tasks.md`

```markdown
# {Feature 名} タスクリスト

## Step 1: Migration & Model
- [ ] migration: create_{table}_table（ULID, SoftDeletes 必須）
- [ ] Model: {Entity}（fillable, casts, リレーション, スコープ）
- [ ] Enum（あれば）
- [ ] Factory

## Step 2: Policy
- [ ] {Entity}Policy（viewAny / view / create / update / delete）
- [ ] AuthServiceProvider 登録 / 自動検出確認

## Step 3: HTTP 層
- [ ] {Entity}Controller スケルトン（薄く）
- [ ] StoreRequest / UpdateRequest（rules + authorize）
- [ ] {Entity}Resource（API の場合）
- [ ] routes/web.php にルート定義

## Step 4: Action / Service
- [ ] IndexAction / ShowAction / StoreAction / UpdateAction / DestroyAction
- [ ] カスタム Action（Controller method 名と一致）
- [ ] {Feature}Service（共有ロジック必要時）
- [ ] ドメイン例外（app/Exceptions/{Domain}/）

## Step 5: Blade ビュー
- [ ] resources/views/{feature}/index.blade.php
- [ ] show / form / etc.
- [ ] Blade コンポーネント（必要時）

## Step 6: テスト
- [ ] tests/Feature/Http/{Entity}/{Action}Test.php（各メソッド + 認可漏れ）
- [ ] tests/Feature/UseCases/{Entity}/{Action}ActionTest.php（カスタムAction）
- [ ] tests/Unit/Services/{Feature}ServiceTest.php（あれば）
- [ ] tests/Unit/Policies/{Entity}PolicyTest.php

## Step 7: 動作確認 & 整形
- [ ] `php artisan test --filter={Entity}` 通過
- [ ] `vendor/bin/pint --dirty` 整形
- [ ] ブラウザでの主要画面動作確認
```

タスクは Step 単位グループ + チェックボックス。1 タスク = 1 コミット粒度。

## 処理フロー

1. `$ARGUMENTS` で Feature 名取得
2. 前提読み込み（CLAUDE.md / docs/steering/ / 依存先 specs）
3. **requirements.md を生成**（EARS 形式、product.md 起点）
4. **design.md 生成前に COACHTECH LMS を調査**:
   - `ls` / `grep` / `find` で対応する Model / Controller / Action / Migration を探索（「参考にする既存実装 → 調査手順」参照）
   - 対応実装ありなら Read して観察パターンをメモ化
   - 対応実装なしなら LMS 業界標準・周辺実装を参考にする方針を確認
5. **design.md を生成**（先頭の「参考実装」セクションで調査結果と根拠を明記、Certify 固有の差異も併記）
6. **tasks.md を生成**（design.md のコンポーネントを Step 順にチェックボックス化）
7. 完了報告（生成行数 + 主要設計判断のサマリ + COACHTECH 調査結果のサマリ）

## 制約

- **`docs/specs/{name}/` 配下以外のファイルを編集しない**
- product.md / tech.md / structure.md / .claude/rules/ との整合性
- 命名は structure.md の規約に厳格に従う
- 1 Skill 実行 = 1 Feature

## 完了基準

- 3ファイルが `docs/specs/{name}/` に存在
- 要件 ID（REQ-{name}-NNN）が design / tasks からトレース可能
- 既存 specs（基盤 Feature）と命名・構造が整合
