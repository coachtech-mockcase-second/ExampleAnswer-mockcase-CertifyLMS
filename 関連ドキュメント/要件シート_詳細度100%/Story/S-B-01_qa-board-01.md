# S-B-01 質問掲示板の実装

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-01` |
| Feature 連番 | `qa-board-01` |
| Feature | qa-board |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Basic |
| 工数 (h) | 13 |
| 依存チケット | `S-B-05`(notification 基盤、新着回答通知を発火するため) |

## 概要

受講生・コーチが資格別に技術質問を **公開で** 投稿・回答する Q&A 掲示板を新規実装する。新規テーブル `qa_threads` / `qa_replies` を追加し、管理者はモデレーション削除のみ可能。

## 背景・目的

現状、受講生は資格学習中の疑問を 1on1 のチャットでコーチに個別質問しているが、同じ疑問が複数受講生で繰り返されることで集合知が蓄積されない。本機能では公開型 Q&A 掲示板を導入し、過去の質問・回答を他受講生も参照可能にして自己解決率を向上させ、コーチの対応工数を削減する。コーチは担当資格の未対応スレッドをサイドバーバッジで把握できる導線も提供する。

## ユーザーストーリー

- **受講生(student)として**、公開済資格すべての掲示板に質問を投稿し、コーチや他受講生から回答を得たい。なぜなら、自分の疑問を集合知として解決したいから。
- **受講生として**、他受講生の質問・回答を閲覧したい。なぜなら、過去のスレッドから自己解決のヒントを得たいから。
- **受講生として**、受講していない資格にも回答したい。なぜなら、自分の知識を他受講生に共有して集合知に貢献したいから。
- **スレッド投稿者として**、自分の質問を解決済にマークしたい。なぜなら、未解決の質問とスレッドの解決状態を区別したいから。
- **コーチ(coach)として**、担当資格の未対応スレッドを一覧から消化したい。なぜなら、複数受講生を効率的にフォローしたいから。
- **コーチとして**、サイドバーで未対応件数を一目で把握したい。なぜなら、対応漏れを防ぎたいから。
- **管理者(admin)として**、不適切投稿をモデレーション削除したい。なぜなら、コミュニティの健全性を保ちたいから。

## スコープ外

- 添付ファイル(画像 / PDF) — テキストのみ
- Section / Question への紐付け — 資格紐付けのみ
- ベスト回答指定 / ネスト回答 / 編集履歴 / いいね・投票 / タグ / メンション通知
- FULLTEXT INDEX / 外部検索エンジン — `LIKE` のみ
- 管理者による投稿内容編集 / 解決マーク代行
- 同時編集競合の楽観ロック

## 受け入れ条件

- [ ] **スレッド投稿 - 成功時動作**: 受講生が「資格(公開済のみ)+ タイトル + 本文」で投稿成功時、`/qa-board/{thread}` にリダイレクトされフラッシュ「質問を投稿しました」が表示される
- [ ] **スレッド投稿 - 認可拒否**: コーチ / 管理者が `/qa-board/create` または POST `/qa-board` にアクセスすると 403
- [ ] **一覧表示 - ロール別フィルタ**: 受講生は公開済資格すべて、コーチは担当資格のみのスレッドが新着順で 20 件/ページ表示される
- [ ] **一覧表示 - N+1 なし**: 一覧で `with(['certification', 'user'])` + `withCount('replies')` により N+1 が発生しない
- [ ] **詳細表示**: スレッド詳細画面で配下回答が新着順で全件表示される(ページネーションなし)
- [ ] **スレッド編集 - 投稿者本人のみ**: 投稿者本人がタイトル・本文を編集できる(資格は変更不可)、投稿者以外がアクセスすると 403
- [ ] **スレッド編集 - フラッシュ**: 編集成功時フラッシュ「質問を更新しました」が表示される
- [ ] **スレッド削除 - 投稿者条件**: 投稿者本人が回答 0 件(SoftDelete 含む)のスレッドのみ削除可、削除時 SoftDelete される
- [ ] **スレッド削除 - 回答ありエラー**: 回答有時は 409 + フラッシュエラー「回答が付いているスレッドは削除できません。」
- [ ] **スレッド削除 - 管理者無条件**: 管理者は任意のスレッドを無条件で SoftDelete 可能
- [ ] **回答 CRUD - 受講生・コーチ**: 受講生 / コーチが回答投稿・編集・自削除でき、成功時フラッシュ表示。管理者は回答投稿不可(403)、管理者は任意の回答を削除可
- [ ] **解決マーク・解除 - 認可**: スレッド投稿者本人のみが解決マーク / 解除でき、管理者 / コーチ / 他受講生は 403(管理者であっても代行不可)
- [ ] **解決マーク・解除 - 状態整合性**: 解決時に `status=resolved` + `resolved_at=now()` を同時更新(逆操作で `status=open` + `resolved_at=null`)、既に解決済 / 未解決に同じ操作で 409
- [ ] **通知連携**: 回答者がスレッド投稿者と異なる場合に database + mail 両方の channel で通知発火、自己回答時はスキップ
- [ ] **検索・フィルタ**: 資格別 / 解決状態 / キーワード(`title` / `body` / 回答 `body` の OR LIKE)で絞り込みでき、ページネーションにフィルタ状態が引き継がれる
- [ ] **列挙攻撃防御**: コーチが担当外資格 ID をクエリ指定すると 403(資格存在は隠蔽しない)
- [ ] **管理者モデレーション**: `/admin/qa-board` で全スレッド一覧 + SoftDelete 含むトグル、`/admin/qa-board/{thread}` で SoftDelete 済の回答も閲覧可、削除は SoftDelete
- [ ] **サイドバーバッジ**: コーチに「質問対応 (N)」(担当資格 × `status=open` × 回答 0 件 の件数)を表示。受講生サイドバーにはバッジなし
- [ ] **公開状態と認可**: コーチが担当外資格のスレッド URL 直叩きで 403、受講生 / コーチが公開停止資格のスレッド URL 直叩きで 404

<!-- coach-only:start -->

## 実装方針 (参考)

> **あくまで参考設計**。様々な実装方法がある前提で、受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### データモデル

**新規テーブル**: `qa_threads` / `qa_replies`(両者とも ULID 主キー + SoftDelete)

`qa_threads`:

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| certification_id | ulid | ✓ | certifications.id, ON DELETE CASCADE | `$table->foreignUlid('certification_id')->constrained()->cascadeOnDelete()` |
| user_id | ulid | ✓ | users.id, ON DELETE CASCADE | `$table->foreignUlid('user_id')->constrained()->cascadeOnDelete()` |
| title | varchar(200) | ✓ | | |
| body | text | ✓ | | |
| status | varchar(20) | ✓ | | `QaThreadStatus` Enum cast (`open` / `resolved`)、デフォルト `open` |
| resolved_at | timestamp | | | NULL 許可、解決時に `now()` セット |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |
| deleted_at | timestamp | | | `$table->softDeletes()` |

`qa_replies`:

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| qa_thread_id | ulid | ✓ | qa_threads.id, ON DELETE CASCADE | `$table->foreignUlid('qa_thread_id')->constrained()->cascadeOnDelete()` |
| user_id | ulid | ✓ | users.id, ON DELETE CASCADE | `$table->foreignUlid('user_id')->constrained()->cascadeOnDelete()` |
| body | text | ✓ | | |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |
| deleted_at | timestamp | | | `$table->softDeletes()` |

- **インデックス**: `(certification_id, status)` 複合 / `user_id` / `(qa_thread_id, created_at)` 複合
- **Enum / Cast**: `QaThreadStatus`(open / resolved)→ `qa_threads.status` に cast、`label()` 戻り値「未解決」「解決済」
- **リレーション**: User 1-N QaThread / Certification 1-N QaThread / QaThread 1-N QaReply / User 1-N QaReply
- **SoftDelete**: 両者採用(配下回答は物理 cascade させず個別 SoftDelete 状態保持)

### 主要画面・操作

**公開エンドポイント**(受講生 / コーチ):

| 画面 | 操作 | URL / メソッド | 受け入れ条件サマリ | 使用技術 |
|---|---|---|---|---|
| スレッド一覧 | ページ表示 | GET /qa-board | ロール別フィルタ + 新着順 20 件/ページ | `QaThreadController@index`, `with(['certification', 'user'])`, `withCount('replies')` |
| スレッド投稿 | フォーム表示 | GET /qa-board/create | 受講生のみ表示、コーチ/管理者は 403 | `QaThreadController@create`, `QaThreadPolicy@create` |
| スレッド投稿 | 送信 | POST /qa-board | バリデーション通過時 `/qa-board/{thread}` リダイレクト + フラッシュ「質問を投稿しました」 | `QaThreadController@store`, `StoreQaThreadRequest`, `CreateQaThreadAction` |
| スレッド詳細 | 表示 | GET /qa-board/{thread} | 配下回答を新着順全件表示 | `QaThreadController@show`, `$thread->load(['user', 'certification', 'replies.user'])` |
| スレッド編集 | フォーム表示 | GET /qa-board/{thread}/edit | 投稿者本人のみ、他は 403 | `QaThreadController@edit`, `QaThreadPolicy@update` |
| スレッド編集 | 送信 | PATCH /qa-board/{thread} | タイトル/本文更新、資格は変更不可、フラッシュ「質問を更新しました」 | `QaThreadController@update`, `UpdateQaThreadRequest`, `UpdateQaThreadAction` |
| スレッド削除 | 送信 | DELETE /qa-board/{thread} | 投稿者本人 × 回答 0 件 のみ SoftDelete、回答有時 409 | `QaThreadController@destroy`, `QaThreadPolicy@delete`, `DeleteQaThreadAction` |
| 解決マーク | 送信 | POST /qa-board/{thread}/resolve | 投稿者本人のみ、`status` + `resolved_at` 同時更新 | `QaThreadController@resolve`, `QaThreadPolicy@resolve`, `ResolveQaThreadAction` |
| 解決解除 | 送信 | POST /qa-board/{thread}/unresolve | 投稿者本人のみ、`status=open` + `resolved_at=null` 同時更新 | `QaThreadController@unresolve`, `QaThreadPolicy@unresolve`, `UnresolveQaThreadAction` |
| 回答投稿 | 送信 | POST /qa-board/{thread}/replies | 受講生/コーチ可、管理者不可。自己回答スキップ条件で通知発火 | `QaReplyController@store`, `StoreQaReplyRequest`, `CreateQaReplyAction`(通知発火含む) |
| 回答編集 | 送信 | PATCH /qa-board/{thread}/replies/{reply} | 投稿者本人のみ | `QaReplyController@update`, `UpdateQaReplyRequest`, `UpdateQaReplyAction` |
| 回答削除 | 送信 | DELETE /qa-board/{thread}/replies/{reply} | 投稿者本人のみ SoftDelete | `QaReplyController@destroy`, `QaReplyPolicy@delete`, `DeleteQaReplyAction` |

**管理者モデレーション**:

| 画面 | 操作 | URL / メソッド | 受け入れ条件サマリ | 使用技術 |
|---|---|---|---|---|
| 全スレッド一覧 | 表示 | GET /admin/qa-board | 全資格、SoftDelete 含むトグル | `Admin\QaThreadController@index`, `withTrashed()` |
| スレッド詳細 (管理者) | 表示 | GET /admin/qa-board/{thread} | SoftDelete 済の回答も閲覧可 | `Admin\QaThreadController@show` |
| モデレーション削除 (スレッド) | 送信 | DELETE /admin/qa-board/{thread} | 無条件 SoftDelete | `Admin\QaThreadController@destroy`, `QaThreadPolicy@moderationDelete` |
| モデレーション削除 (回答) | 送信 | DELETE /admin/qa-board/replies/{reply} | 任意の回答を SoftDelete | `Admin\QaReplyController@destroy`, `QaReplyPolicy@moderationDelete` |

### バリデーション

**FormRequest**: `StoreQaThreadRequest` / `UpdateQaThreadRequest` / `StoreQaReplyRequest` / `UpdateQaReplyRequest`

`StoreQaThreadRequest`:

| 入力項目 | ルール | エラーメッセージ |
|---|---|---|
| certification_id | required / ulid / exists:certifications,id(公開済のみ) | 資格を選択してください。<br>選択された資格は存在しません。 |
| title | required / string / max:200 / not_regex:全角空白のみ | タイトルは必須です。<br>タイトルは 200 文字以内で入力してください。<br>タイトルに有効な文字を入力してください。 |
| body | required / string / max:5000 | 本文は必須です。<br>本文は 5000 文字以内で入力してください。 |

`UpdateQaThreadRequest`:

| 入力項目 | ルール | エラーメッセージ |
|---|---|---|
| title | required / string / max:200 | 同上 |
| body | required / string / max:5000 | 同上 |

> 注: `certification_id` は受け取らない(資格変更不可)。

`StoreQaReplyRequest` / `UpdateQaReplyRequest`:

| 入力項目 | ルール | エラーメッセージ |
|---|---|---|
| body | required / string / max:5000 | 本文は必須です。<br>本文は 5000 文字以内で入力してください。 |

### 認可設計

**Policy**: `QaThreadPolicy` / `QaReplyPolicy`(エンティティ単位で 2 分割)

`QaThreadPolicy`:

| メソッド | ロール × 判定 |
|---|---|
| viewAny | 受講生: 公開済資格 ✅ / コーチ: 担当資格のみ ✅ / 管理者: 全資格 ✅ |
| view | コーチ担当外資格 → 403(列挙攻撃防御)/ 公開停止資格 → 404 |
| create | 受講生のみ ✅ |
| update | 投稿者本人のみ ✅(解決済も可) |
| delete | 投稿者本人 × 回答 0 件 ✅ / 管理者: 無条件 ✅ |
| resolve / unresolve | 投稿者本人のみ ✅(管理者であっても代行不可) |
| moderationDelete | 管理者のみ ✅ |

`QaReplyPolicy`:

| メソッド | ロール × 判定 |
|---|---|
| create | 受講生 / コーチ ✅(管理者不可) |
| update | 投稿者本人のみ ✅ |
| delete | 投稿者本人のみ ✅ / 管理者: 任意の回答 ✅ |
| moderationDelete | 管理者のみ ✅ |

### Seeder 設計

| Seeder | 投入データ | 件数目安 | 備考 |
|---|---|---|---|
| `QaThreadSeeder` | 動作確認用シナリオ: 受講生 A の自己投稿(解決済 / 未解決)+ 受講生 B の投稿 + 回答 0 件(削除可テスト用)+ 回答ありで削除不可テスト用 + 公開停止資格スレッド(404 テスト用) | 6〜8 件 | `create` |
| `QaReplySeeder` | 各スレッドに 0〜3 件の回答。自己回答スキップシナリオ(投稿者 = 回答者)を 1 件含める。回答有スレッドは削除不可テスト用に必ず 1 件以上回答を持たせる | 10〜15 件 | `create` |

- **DatabaseSeeder への追加順序**: `UserSeeder` → `CertificationSeeder` → `QaThreadSeeder` → `QaReplySeeder`

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `QaThreadStatus` Enum(cast / `label()` 日本語)/ QaThread リレーション(user, certification, replies)/ QaReply リレーション(qaThread, user) |
| Feature | 各エンドポイントの認可分岐(受講生 / コーチ / 管理者)/ 副作用(DB 行追加・更新、SoftDelete、通知発火)/ フラッシュ文言 / リダイレクト先 / 列挙攻撃(担当外資格 ID クエリ指定で 403)/ 検索・フィルタのページネーション引き継ぎ |
| Policy | 三重判定(ロール × 当事者 × 担当資格)のネットワーク・ケース網羅 / `resolve` は管理者代行不可 / `delete` は投稿者 × 回答 0 件 / 管理者無条件 |
| 通知 | `Notification::fake()` で発火を検証 / 自己回答時はスキップ / 回答者 ≠ 投稿者で database + mail 両 channel 発火 |

### アーキテクチャ判断

- **採用技術**: Eloquent + UseCases(Action)+ Policy + FormRequest + Blade(提供済み)+ Notification(database + mail channel)+ `DB::transaction`
- **設計判断**:
  1. **エンドポイント分離**: 公開(受講生 / コーチ)と管理者モデレーションで Controller / Action / Policy メソッドを分離(認可ルールの違いを設計レベルで明示)
  2. **状態整合性**: `status` Enum + `resolved_at` datetime を Action 内同時更新で「status=resolved ⇔ resolved_at != null」担保(他 Feature の `Enrollment.status + passed_at` と整合)
  3. **通知連携**: 自己回答スキップは notification 側ラッパー Action 内で処理、channel は database + mail 固定送信(ユーザー設定 UI なし)
  4. **列挙攻撃防御**: コーチ担当外資格 ID 指定時は 404 ではなく 403(資格存在は隠蔽しない)。一方、公開停止資格は 404(資格自体が見えない = リソース存在しない扱い)
  5. **XSS 防御**: `{!! nl2br(e($body)) !!}` パターン、Markdown レンダリングしない
  6. **検索**: `LIKE` のみ(FULLTEXT INDEX / 外部全文検索エンジン不採用)、検索対象は `QaThread.title` / `body` / `QaReply.body` の OR 部分一致
  7. **SoftDelete**: スレッド・回答とも SoftDelete、配下回答は物理 cascade させない(個別 SoftDelete 状態保持で履歴閲覧可)

### 主要関連ファイル

- `app/Models/QaThread.php` / `app/Models/QaReply.php`
- `app/Enums/QaThreadStatus.php`
- `app/Http/Controllers/QaThreadController.php` / `app/Http/Controllers/QaReplyController.php`
- `app/Http/Controllers/Admin/QaThreadController.php` / `app/Http/Controllers/Admin/QaReplyController.php`
- `app/UseCases/QaThread/{Create,Update,Delete,Resolve,Unresolve}QaThreadAction.php`
- `app/UseCases/QaReply/{Create,Update,Delete}QaReplyAction.php`
- `app/Policies/QaThreadPolicy.php` / `app/Policies/QaReplyPolicy.php`
- `app/Http/Requests/{Store,Update}QaThread{,Reply}Request.php`
- `app/Exceptions/QaBoard/{QaThreadHasReplies,QaThreadAlreadyResolved,QaThreadNotResolved}Exception.php`
- `resources/views/qa-thread/*.blade.php` / `resources/views/admin/qa-thread/*.blade.php`
- `database/migrations/*_create_qa_threads_table.php` / `database/migrations/*_create_qa_replies_table.php`
- `app/View/Composers/SidebarBadgeComposer.php`(コーチサイドバーバッジ集計)

## 補足

### 想定ヒアリング Q&A

> 3 階層(必須回答 / 実装判断 / 補足)で記述。コーチが受講生からヒアリングされた時の即答材料。

#### 必須回答(バリデーション・認可・文言)

| 質問 | 回答 |
|---|---|
| タイトル / 本文の最大文字数は? | タイトル 200 文字 / 本文 5000 文字 / 回答本文 5000 文字、全角空白のみは `not_regex` で拒否 |
| コーチが担当外資格のスレッドを直叩きしたら 403 / 404? | **403**(担当外であることを明示、資格存在は隠蔽しない) |
| 公開停止資格のスレッドにアクセスしたら 403 / 404? | **404**(資格自体が見えない = リソース存在しない扱い) |
| フラッシュメッセージの文言は? | 投稿「質問を投稿しました」/ 編集「質問を更新しました」/ 削除「質問を削除しました」/ 削除エラー「回答が付いているスレッドは削除できません。」/ 回答投稿「回答を投稿しました」/ 解決「解決済にしました」/ 解除「未解決に戻しました」 |
| スレッド削除条件は? | 投稿者本人 × 回答 0 件(SoftDelete 含む)のみ。管理者は無条件 |
| 解決マーク / 解除は管理者も代行できる? | いいえ、投稿者本人のみ(管理者であっても代行不可) |

#### 実装判断(設計レベル)

| 質問 | 回答 |
|---|---|
| 削除は SoftDelete / 物理削除? | SoftDelete(履歴保持)、配下回答は物理 cascade させない(個別 SoftDelete 状態保持) |
| 通知は database / mail どちらの channel? | 両方固定送信(ユーザー設定 UI なし) |
| 自己回答時に通知発火させる? | いいえ、スキップ(回答者 = 投稿者の場合) |
| キーワード検索は LIKE / FULLTEXT? | `LIKE` のみ、検索対象は `QaThread.title` / `body` / `QaReply.body` の OR 部分一致 |
| ページネーションは何件 / ページ? | 20 件(回答はページネーションなし、詳細画面で全件表示) |
| 一覧 Eager Loading は何を指定? | `with(['certification', 'user'])` + `withCount('replies')` |

#### 補足

| 質問 | 回答 |
|---|---|
| Policy はクラス分割する? | はい、`QaThreadPolicy` / `QaReplyPolicy` の 2 つ |
| サイドバーバッジ集計はどこで? | `SidebarBadgeComposer` の coach 分岐に COUNT クエリ追加 |
| テストは Feature / Unit どっち? | 両方。Feature で各エンドポイント認可 + 副作用 + フラッシュ、Unit で Policy の三重判定 |
| 管理者はスレッド / 回答の内容編集できる? | いいえ、閲覧 + 削除のみ |
| `EnsureActiveLearning` Middleware の影響は? | 全受講生 / コーチルートに適用(graduated 受講生をブロック、コーチは影響なし) |
| 同時編集競合の楽観ロックは? | 持たない(教育 PJ スコープ外) |

<!-- coach-only:end -->
