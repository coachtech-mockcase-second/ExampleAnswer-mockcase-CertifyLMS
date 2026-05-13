# tasks.md テンプレート

`docs/specs/{name}/tasks.md` の構造とフォーマット規約。

各タスクには **関連要件 ID を inline 注釈**（`（REQ-{name}-XXX, REQ-{name}-YYY）`）で付ける。これにより tasks → requirements の逆引きが成立し、PR レビュー時に「どの要件を満たすコミットか」が一目でわかる。

## ファイル構造

```markdown
# {Feature 名} タスクリスト

> 1タスク = 1コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-{name}-NNN` / `NFR-{name}-NNN` を参照。

## Step 1: Migration & Model
- [ ] migration: create_{table}_table（ULID, SoftDeletes 必須）（REQ-{name}-XXX）
- [ ] Model: {Entity}（fillable, casts, リレーション, スコープ）（REQ-{name}-XXX）
- [ ] Enum: {EnumName}（label() 含む）（REQ-{name}-XXX）
- [ ] Factory: {state1}() / {state2}() state 提供

## Step 2: Policy
- [ ] {Entity}Policy（viewAny / view / create / update / delete）（REQ-{name}-XXX）
- [ ] AuthServiceProvider に登録 or 自動検出確認

## Step 3: HTTP 層
- [ ] {Entity}Controller スケルトン（薄く保つ）（REQ-{name}-XXX）
- [ ] StoreRequest / UpdateRequest（rules + authorize）（REQ-{name}-XXX）
- [ ] {Entity}Resource（API の場合）
- [ ] routes/web.php / routes/api.php にルート定義（REQ-{name}-XXX）

## Step 4: Action / Service / Exception
- [ ] IndexAction / ShowAction / StoreAction / UpdateAction / DestroyAction（REQ-{name}-XXX）
- [ ] カスタム Action（Controller method 名と一致）（REQ-{name}-XXX）
- [ ] {Feature}Service（共有ロジック必要時）（REQ-{name}-XXX）
- [ ] ドメイン例外（app/Exceptions/{Domain}/）（NFR-{name}-XXX）

## Step 5: Blade ビュー
- [ ] resources/views/{feature}/index.blade.php
- [ ] show / form / etc.
- [ ] Blade コンポーネント（必要時）

## Step 6: テスト
- [ ] tests/Feature/Http/{Entity}/{Action}Test.php（正常系 + バリデーション失敗 + 認可漏れ）
  - `test_{role}_can_{action}_{resource}`
  - `test_{role}_cannot_{action}_other_users_{resource}`
- [ ] tests/Feature/UseCases/{Entity}/{Action}ActionTest.php（カスタム Action の正常系 + 異常系）
- [ ] tests/Unit/Services/{Feature}ServiceTest.php（純粋ロジック、あれば）
- [ ] tests/Unit/Policies/{Entity}PolicyTest.php（ロール×操作の真偽値網羅）

## Step 7: 動作確認 & 整形
- [ ] `sail artisan test --filter={Entity}` 通過
- [ ] `sail bin pint --dirty` 整形
- [ ] ブラウザでの主要画面動作確認（通しシナリオを箇条書き）
- [ ] Schedule Command / Queue Job の動作確認（該当時、`sail artisan {command}` 手動実行）
```

## 規約

- タスクは Step 単位グループ + チェックボックス。1 タスク = 1 コミット粒度
- **全タスク末尾に関連要件 ID を inline 注釈**で付ける（テスト系・整形系・動作確認系など要件 ID 不要なタスクは省略可）
- **コマンドは Sail プレフィックス必須**: 開発環境は Laravel Sail。`sail artisan ...` / `sail npm ...` / `sail bin pint` 形式（`tech.md` の「コマンド慣習」セクション参照）。`php artisan` / `vendor/bin/pint` をホスト側で直叩く書き方は使わない
