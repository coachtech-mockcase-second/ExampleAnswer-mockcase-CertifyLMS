# {チケットID} {タイトル}

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-XX` / `S-A-XX` |
| Feature 連番 | `{Feature略称}-{連番}` |
| Feature | {Feature 名} |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 / 既存機能の拡張 |
| 難易度 | Basic / Advance |
| 工数 (h) | XX |
| 依存チケット | (なし / `S-B-XX`) ← **このチケットが依存する側** を記録(逆向きは書かない) |

## 概要

> 何を作るか(What) を 1〜2 文で簡潔に。

## 背景・目的

> なぜこのチケットがあるか(現状のどんな問題があるか) + 達成したい状態(目的)を 2〜4 文で。プロダクトの文脈を記述。

## ユーザーストーリー

> Connextra 形式(`{ロール}として、{何を}したい。なぜなら、{目的}から`)。ロール別に 1 行ずつ。Why を明示する装置として 30% 版でも保持する。

- **{ロール A}として**、{何を}したい。なぜなら、{目的}から。
- **{ロール B}として**、{何を}したい。なぜなら、{目的}から。

## スコープ外

- (スコープ外 1)
- (スコープ外 2)

## 受け入れ条件

> 各項目 = 1 採点行(評価シート ① の 1 行)。Story は 8〜12 項目を目安。書き方原則は `../README.md#受け入れ条件の書き方原則` 参照(振る舞いベース + 1 項目 1 振る舞い + MECE)。
> 横断品質(自動テスト pass / PR 7 セクション完備 / 動画)は **評価シート ③ 横断ドキュメント** で別管理。本セクションには含めない。

- [ ] {採点項目 1}
- [ ] {採点項目 2}

<!-- coach-only:start -->

## 実装方針 (参考)

> **あくまで参考設計**。様々な実装方法がある前提で、受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。BookShelf 詳細度 100% シート相当の粒度(画面操作 / バリデーション / テーブル定義 / 認可設計 / API / Seeder / テスト観点)を含めて記載する。

### データモデル

**新規テーブル**: `{table_name}` (該当する場合)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| user_id | ulid | ✓ | users.id, ON DELETE CASCADE | `$table->foreignUlid('user_id')->constrained()->cascadeOnDelete()` |
| ... | ... | | | |

- **インデックス**: `(user_id, status)` 等
- **Enum / Cast**: `{EnumClass}` 等
- **リレーション**: User 1-N {Entity} 等
- **SoftDelete**: 採用 / 不採用

### 主要画面・操作

> URL / メソッドはここに集約(チケット本体に独立した「主要 URL」セクションは持たない)。

| 画面 | 操作 | URL / メソッド | 受け入れ条件サマリ | 使用技術 |
|---|---|---|---|---|
| {画面名} | ページ表示 | GET /xxx | ... が表示される | `{Controller}@{method}`, Blade |
| {画面名} | フォーム送信 | POST /xxx | バリデーション通過時→...、失敗時→... | `{Controller}@store`, `{FormRequest}` |

### バリデーション

**FormRequest**: `Store{Entity}Request` / `Update{Entity}Request`

| 入力項目 | ルール | エラーメッセージ |
|---|---|---|
| name | required / string / max:255 | name.required: 名前は必須です。<br>name.max: 名前は255文字以内で入力してください。 |
| ... | ... | ... |

### 認可設計

**Policy**: `{Entity}Policy`

| メソッド | ロール × 判定 |
|---|---|
| viewAny | 管理者(admin): ✅ / コーチ(coach): 担当資格内のみ ✅ / 受講生(student): 自分のみ ✅ |
| view | 管理者: ✅ / コーチ: 担当資格内 / 受講生: 自分 |
| create | 受講生のみ ✅ |
| update | 投稿者本人 / 管理者 |
| delete | 投稿者本人(条件付き) / 管理者 |

### API 仕様 (該当する場合)

| エンドポイント | リクエスト | レスポンス | 認証 |
|---|---|---|---|
| GET /api/v1/xxx | - | JSON 配列 | なし / `auth:sanctum` |

### Seeder 設計

> **新規テーブル追加時の初期データ仕様**(該当する場合)。動作確認しやすい代表シナリオを `database/seeders/` に投入。

| Seeder | 投入データ | 件数目安 | 備考 |
|---|---|---|---|
| `{Entity}Seeder` | 動作確認しやすい代表シナリオ(自己投稿 / 他人投稿 / 解決済 / 未解決 等) | 5〜10 件 | `firstOrCreate` / `create` で実装 |

- **DatabaseSeeder への追加順序**: 依存 Seeder(例: UserSeeder, CertificationSeeder)の後
- **動作確認用シナリオ**: 認可テストで使える主要ロールデータを必ず含める

### テスト観点

> **チケット固有の検証観点**(横断品質「全テスト pass」「カバレッジ達成率」は評価シート ② 横断で別管理)。

| 種別 | 観点 |
|---|---|
| Unit | Model リレーション / Scope / Enum label / ドメイン例外 |
| Feature | エンドポイント認可分岐(各ロール)/ 副作用(DB 行追加・更新 / 通知発火)/ フラッシュ文言 / リダイレクト先 |
| Policy | ロール × 当事者 × {追加条件} の 3 軸判定 |

### アーキテクチャ判断

- **採用技術**: Eloquent + UseCases (Action) + Policy + FormRequest + Blade + 通知 (DatabaseChannel) + `DB::transaction` 等
- **設計判断**:
  1. **画面分離**: 公開(受講生 / コーチ)vs 管理者 で Controller / Action を分離(認可ルールの違いを設計レベルで明示)
  2. **状態整合性**: Enum + datetime カラムを Action 内同時更新
  3. **XSS 防御**: `{!! nl2br(e($body)) !!}` パターン

### 主要関連ファイル

> コードリーディング起点。受講生から「どこを見れば良い?」と聞かれた時の案内用。

- `app/Models/{Model}.php`
- `app/Http/Controllers/{Controller}.php`
- `app/UseCases/{Feature}/{Action}.php`
- `app/Policies/{Policy}.php`
- `resources/views/{feature}/...blade.php`

## 補足

### 想定ヒアリング Q&A

> 3 階層(必須回答 / 実装判断 / 補足)で記述。コーチが受講生からヒアリングされた時の即答材料。

#### 必須回答(バリデーション・認可・文言)

| 質問 | 回答 |
|---|---|
| ... | ... |

#### 実装判断(設計レベル)

| 質問 | 回答 |
|---|---|
| ... | ... |

#### 補足

| 質問 | 回答 |
|---|---|
| ... | ... |

<!-- coach-only:end -->
