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
| 依存チケット | (なし) |

## 概要

受講生・コーチが資格別に技術質問を **公開で** 投稿・回答する Q&A 掲示板を新規実装する。質問スレッドと回答の 2 種類のデータを新規追加し、管理者はモデレーション削除のみ可能。

## 背景・目的

- **現状の問題**: 受講生は資格学習中の疑問を 1on1 のチャット(chat Feature、提供 PJ に完成済として同梱)でコーチに個別質問しているが、同じ疑問が複数受講生で繰り返されるため集合知が蓄積されない。コーチも類似質問への対応が重複し工数が膨らむ。
- **達成したい状態**: 公開型 Q&A 掲示板を導入し、過去の質問・回答が他受講生にも参照可能になる。受講生は自己解決率が向上し、コーチは未対応スレッドだけをフォローすればよい状態にする。
- **価値・優先度**: 集合知蓄積による **受講生の学習効率向上** + **コーチの対応工数削減** が同時に得られる中核機能。

## ユーザーストーリー

- **受講生(student)として**、公開済資格すべての掲示板に質問を投稿し、コーチや他受講生から回答を得たい。なぜなら、自分の疑問を集合知として解決したいから。
- **受講生として**、他受講生の質問・回答を閲覧したい。なぜなら、過去のスレッドから自己解決のヒントを得たいから。
- **受講生として**、受講していない資格にも回答したい。なぜなら、自分の知識を他受講生に共有して集合知に貢献したいから。
- **スレッド投稿者として**、自分の質問を解決済にマークしたい。なぜなら、未解決の質問とスレッドの解決状態を区別したいから。
- **コーチ(coach)として**、担当資格の未対応スレッドを一覧から消化したい。なぜなら、複数受講生を効率的にフォローしたいから。
- **管理者(admin)として**、不適切投稿をモデレーション削除したい。なぜなら、コミュニティの健全性を保ちたいから。

## やること

### スレッド

- **投稿**: 受講生のみ可(資格選択 + タイトル + 本文)、コーチ / 管理者は 403
- **編集**: 投稿者本人のみ可(タイトル + 本文、資格は変更不可)、他は 403
- **削除(自削除)**: 投稿者本人 × 回答 0 件のみ可(物理削除)、回答ありで 409
- **削除(モデレーション)**: 管理者は任意のスレッドを無条件で物理削除可(配下回答も連動削除)
- **解決マーク・解除**: スレッド投稿者本人のみ可(解決状態と解決日時を同時更新)、管理者でも代行不可

### 回答

- **投稿**: 受講生 / コーチ可、管理者は 403
- **編集・自削除**: 投稿者本人のみ可(物理削除)
- **削除(モデレーション)**: 管理者は任意の回答を無条件で物理削除可

### 閲覧・検索

- **スレッド一覧 / 詳細閲覧**:
  - 受講生: 公開済資格すべて
  - コーチ: 担当資格のみ(担当外資格をクエリパラメータで指定しても 403、列挙攻撃防御)
  - 管理者: 全資格(公開停止資格も含む)を横断閲覧可、公開画面と同一 Blade を `/admin/qa-board` 経由で開く
- **検索・フィルタ**: 資格別 / 解決状態 / キーワード(タイトル / 本文 / 回答本文の OR 部分一致)+ ページネーション(20 件/ページ、フィルタ引き継ぎ)

### 共通の振る舞い

- 公開停止資格のスレッドは受講生・コーチには 404(資格自体が見えない扱い)、管理者には引き続き閲覧可

## やらないこと

- 添付ファイル(画像 / PDF) — テキストのみ
- Section / Question への紐付け — 資格紐付けのみ
- ベスト回答指定 / ネスト回答 / 編集履歴 / いいね・投票 / タグ / メンション通知
- FULLTEXT INDEX / 外部検索エンジン — 部分一致検索のみ
- 管理者による投稿内容編集 / 解決マーク代行
- 同時編集競合の楽観ロック
- SoftDelete / 削除済表示トグル — 物理削除のみ(削除履歴は保持しない)
- 1on1 チャット機能 — 別 Feature(chat)で提供 PJ に完成済として組み込み済み
- **通知発火** — notification Feature のスコープ(S-B-04 で扱う)。本チケットでは扱わない
- **サイドバーバッジ(未対応件数表示)** — 採点上重要度が低く本機能は廃止

## Seeder 設計

> `migrate:fresh --seed` 直後に動作確認できるよう、シナリオに紐付けたレコード単位で具体化する。

**前提**(他 Seeder で投入される想定): 受講生 A〜D / コーチ X(資格 X 担当) / コーチ Y(資格 Z 担当) / 管理者 / 公開資格 X, Z / 公開停止資格 Y

`QaThreadSeeder`:

| レコード | 内容 | 動作確認用途 |
|---|---|---|
| thread_1 | 資格 X / 受講生 A 投稿 / status=open / 回答 0 件 | 投稿者本人による削除可動作(物理削除確認) |
| thread_2 | 資格 X / 受講生 B 投稿 / status=open / 回答 3 件(`reply_1`〜`reply_3`) | 投稿者本人 × 回答ありでの削除不可動作(409)/ 検索キーワードヒット |
| thread_3 | 資格 X / 受講生 A 投稿 / status=resolved / `resolved_at` セット済 / 回答 2 件 | 解決済スレッドの編集 / 管理者による無条件削除(配下回答含む)/ 解決解除 |
| thread_4 | 公開停止資格 Y / 受講生 A 投稿 / status=open | 公開停止資格スレッドの受講生 404 / 管理者は閲覧可 |
| thread_5 | 資格 Z(コーチ Y 担当) / 受講生 C 投稿 / status=open / 回答 1 件 | コーチ X からの担当外資格アクセスで 403(列挙攻撃防御) |

`QaReplySeeder`:

| レコード | 内容 | 動作確認用途 |
|---|---|---|
| reply_1 | thread_2 への回答(受講生 B = 投稿者の自己回答) | 投稿者本人による回答編集・削除 |
| reply_2 | thread_2 への回答(受講生 C) | 他受講生の回答に対するアクセス制限 |
| reply_3 | thread_2 への回答(コーチ X) | コーチによる回答の編集・削除 |
| reply_4 | thread_3 への回答(受講生 D) | 解決済スレッド配下の回答編集 / 管理者による無条件回答削除 |
| reply_5 | thread_5 への回答(受講生 D) | 列挙攻撃防御の周辺データ |

- **DatabaseSeeder への追加順序**: `UserSeeder` → `CertificationSeeder` → `QaThreadSeeder` → `QaReplySeeder`

## 受け入れ条件

- [ ] **スレッド投稿 - リダイレクト**: 受講生が投稿成功時、`/qa-board/{thread}`(作成されたスレッド詳細画面)にリダイレクトされる
- [ ] **スレッド投稿 - フラッシュ**: 受講生が投稿成功時、フラッシュメッセージが表示される
- [ ] **スレッド投稿 - 認可拒否**: コーチ / 管理者が投稿フォーム表示 または 投稿アクションにアクセスすると 403
- [ ] **一覧表示 - ロール別フィルタ**: 受講生は公開済資格すべて、コーチは担当資格のみのスレッドが新着順で 20 件/ページ表示される。管理者は `/admin/qa-board` で全資格(公開停止含む)を横断閲覧できる
- [ ] **一覧表示 - クエリ効率**: スレッド一覧の取得で関連データ(投稿者 / 資格 / 回答数)を一括取得し、件数が増えても 1 ページの取得時間が線形に増えない(N+1 が発生しない)
- [ ] **詳細表示**: スレッド詳細画面で配下回答が新着順で全件表示される(回答はページネーションなし)
- [ ] **スレッド編集 - 投稿者本人のみ**: 投稿者本人がタイトル・本文を編集できる(資格は変更不可)、投稿者以外がアクセスすると 403
- [ ] **スレッド編集 - フラッシュ**: 編集成功時、フラッシュメッセージが表示される
- [ ] **スレッド削除 - 投稿者条件**: 投稿者本人が回答 0 件のスレッドのみ物理削除できる
- [ ] **スレッド削除 - 回答ありエラー**: 回答有のスレッドを投稿者が削除しようとすると 409 + フラッシュエラーが表示される
- [ ] **スレッド削除 - 管理者無条件**: 管理者は任意のスレッドを無条件で物理削除可能(配下回答も連動削除)
- [ ] **回答 CRUD - 受講生・コーチ**: 受講生 / コーチが回答投稿・編集・自削除でき、成功時フラッシュ表示
- [ ] **回答 CRUD - 管理者制限**: 管理者は回答投稿不可(403)、管理者は任意の回答を物理削除可
- [ ] **解決マーク・解除 - 認可**: スレッド投稿者本人のみが解決マーク / 解除でき、管理者 / コーチ / 他受講生は 403(管理者であっても代行不可)
- [ ] **解決マーク・解除 - 状態整合性**: 解決マーク時に解決状態と解決日時を同時更新、解除時に未解決状態と解決日時クリアを同時更新する(解決状態と解決日時の整合が崩れない)
- [ ] **解決マーク・解除 - 重複操作エラー**: 既に解決済 / 未解決に同じ操作で 409
- [ ] **検索・フィルタ**: 資格別 / 解決状態 / キーワード(タイトル / 本文 / 回答本文の OR 部分一致)で絞り込みでき、ページネーションにフィルタ状態が引き継がれる
- [ ] **列挙攻撃防御**: コーチが担当外資格をクエリパラメータで指定しても 403(資格存在は隠蔽しない)
- [ ] **管理者モデレーション**: 管理者は `/admin/qa-board` で全スレッド(公開停止資格含む)を横断閲覧可、任意のスレッド / 回答を物理削除可、削除ボタンが Blade に表示される
- [ ] **公開状態と認可**: コーチが担当外資格のスレッドを直接開こうとすると 403、受講生 / コーチが公開停止資格のスレッドを直接開こうとすると 404(管理者は閲覧可)

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

**公開エンドポイント**(受講生 / コーチ):

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/qa-board` | スレッド一覧(ロール別フィルタ + 新着順 20 件/ページ) |
| GET | `/qa-board/create` | 投稿フォーム表示(受講生のみ、コーチ/管理者は 403) |
| POST | `/qa-board` | スレッド投稿、成功時 `/qa-board/{thread}` リダイレクト + フラッシュ「質問を投稿しました」 |
| GET | `/qa-board/{thread}` | スレッド詳細(配下回答を新着順全件表示) |
| GET | `/qa-board/{thread}/edit` | 編集フォーム(投稿者本人のみ、他は 403) |
| PATCH | `/qa-board/{thread}` | スレッド編集(資格は変更不可、フラッシュ「質問を更新しました」) |
| DELETE | `/qa-board/{thread}` | スレッド削除(投稿者本人 × 回答 0 件 のみ物理削除、回答有時 409 + フラッシュエラー「回答が付いているスレッドは削除できません。」) |
| POST | `/qa-board/{thread}/resolve` | 解決マーク(投稿者本人のみ、`status` + `resolved_at` 同時更新、フラッシュ「解決済にしました」) |
| POST | `/qa-board/{thread}/unresolve` | 解決解除(投稿者本人のみ、`status=open` + `resolved_at=null` 同時更新、フラッシュ「未解決に戻しました」) |
| POST | `/qa-board/{thread}/replies` | 回答投稿(受講生/コーチ可、管理者不可、フラッシュ「回答を投稿しました」) |
| GET | `/qa-board/{thread}/replies/{reply}/edit` | 回答編集フォーム(投稿者本人のみ、他は 403、JS インライン編集でなく専用ページ) |
| PATCH | `/qa-board/{thread}/replies/{reply}` | 回答編集(投稿者本人のみ、フラッシュ「回答を更新しました」) |
| DELETE | `/qa-board/{thread}/replies/{reply}` | 回答削除(投稿者本人のみ物理削除、フラッシュ「回答を削除しました」) |

**管理者モデレーション**(同じ `QaThreadController` / `QaReplyController` を異なる route 名で呼ぶ):

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/admin/qa-board` | 全スレッド一覧(全資格・公開停止含む横断、公開画面と同一 Blade を `$isAdminContext` で出し分け表示) |
| GET | `/admin/qa-board/{thread}` | スレッド詳細(公開画面と同一 Blade、admin 用「モデレーション削除」ボタン表示) |
| DELETE | `/admin/qa-board/{thread}` | スレッド モデレーション削除(無条件物理削除、配下回答も連動削除) |
| DELETE | `/admin/qa-board/{thread}/replies/{reply}` | 回答 モデレーション削除(任意の回答を物理削除) |

### データモデル

**新規テーブル**: `qa_threads` / `qa_replies`(両者とも ULID 主キー、SoftDelete は採用しない物理削除)

`qa_threads`:

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| certification_id | ulid | ✓ | certifications.id, ON DELETE RESTRICT | `$table->foreignUlid('certification_id')->constrained()->restrictOnDelete()` |
| user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | `$table->foreignUlid('user_id')->constrained()->restrictOnDelete()` |
| title | varchar(200) | ✓ | | |
| body | text | ✓ | | |
| status | varchar(20) | ✓ | | `QaThreadStatus` Enum cast(`open` / `resolved`)、デフォルト `open` |
| resolved_at | timestamp | | | NULL 許可、解決時に `now()` セット |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

`qa_replies`:

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| qa_thread_id | ulid | ✓ | qa_threads.id, ON DELETE RESTRICT | `$table->foreignUlid('qa_thread_id')->constrained()->restrictOnDelete()` |
| user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | `$table->foreignUlid('user_id')->constrained()->restrictOnDelete()` |
| body | text | ✓ | | |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |

- **インデックス**: `(certification_id, status)` 複合 / `user_id` / `(qa_thread_id, created_at)` 複合
- **Enum / Cast**: `QaThreadStatus`(open / resolved)→ `qa_threads.status` に cast、`label()` 戻り値「未解決」「解決済」
- **リレーション**: User 1-N QaThread / Certification 1-N QaThread / QaThread 1-N QaReply / User 1-N QaReply
- **削除戦略**: 物理削除のみ採用(SoftDelete 不採用)。スレッドの管理者モデレーション削除時は配下回答を Action 内で先に物理削除してから親スレッドを削除する

### バリデーション

`StoreQaThreadRequest`:

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| certification_id | required / ulid / exists:certifications,id(公開済のみ) | 資格を選択してください。<br>選択された資格は存在しません。 |
| title | required / string / max:200 / not_regex:全角空白のみ | タイトルは必須です。<br>タイトルは 200 文字以内で入力してください。<br>タイトルに有効な文字を入力してください。 |
| body | required / string / max:5000 | 本文は必須です。<br>本文は 5000 文字以内で入力してください。 |

`UpdateQaThreadRequest`: `title` / `body` のみ受け取り(資格変更不可)、ルールは Store と同じ。

`StoreQaReplyRequest` / `UpdateQaReplyRequest`:

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| body | required / string / max:5000 | 本文は必須です。<br>本文は 5000 文字以内で入力してください。 |

`IndexQaThreadRequest`: 共通(受講生 / コーチ / admin)。`certification_id` / `status` / `keyword` / `page` を受け取る。authorize は `viewAny`(全ロール許可)で、admin は IndexAction 内で全資格・全状態返却。

### 認可設計

**Policy**: `QaThreadPolicy` / `QaReplyPolicy`(エンティティ単位で 2 分割、admin 全権分岐は Policy 内で `match`)

`QaThreadPolicy`:

| メソッド | ロール × 判定 |
|---|---|
| viewAny | 受講生: 公開済資格 ✅ / コーチ: 担当資格のみ ✅ / 管理者: 全資格 ✅ |
| view | コーチ担当外資格 → 403(列挙攻撃防御) / 公開停止資格 → 受講生・コーチは 404、admin は閲覧可 |
| create | 受講生のみ ✅ |
| update | 投稿者本人のみ ✅(解決済も可) |
| delete | 投稿者本人 × 回答 0 件 ✅ / 管理者: 無条件 ✅(モデレーション削除も同メソッドで分岐) |
| resolve / unresolve | 投稿者本人のみ ✅(管理者であっても代行不可) |

`QaReplyPolicy`:

| メソッド | ロール × 判定 |
|---|---|
| create | 受講生 / コーチ ✅(管理者不可) |
| update | 投稿者本人のみ ✅ |
| delete | 投稿者本人のみ ✅ / 管理者: 任意の回答 ✅(モデレーション削除も同メソッドで分岐) |

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `QaThreadStatus` Enum(cast / `label()` 日本語)/ QaThread リレーション(user, certification, replies)/ QaReply リレーション(qaThread, user) |
| Feature | 各エンドポイントの認可分岐(受講生 / コーチ / 管理者)/ 副作用(DB 行追加・物理削除)/ フラッシュ表示有無 / リダイレクト先パス / 列挙攻撃(担当外資格 ID クエリ指定で 403)/ 検索・フィルタのページネーション引き継ぎ / admin の `/admin/qa-board` 経由アクセスでの全資格横断閲覧 |
| Policy | 三重判定(ロール × 当事者 × 担当資格)のネットワーク・ケース網羅 / `resolve` は管理者代行不可 / `delete` は投稿者 × 回答 0 件 / 管理者無条件 |

### アーキテクチャ判断

> **Basic 範囲制約(規約 `ticket-spec.md` 参照)**: Service クラスは使わない。Action(UseCases)も教材範囲外なので **Controller 内完結を前提** とする。Action 採用は **受講生の判断で推奨**(チャレンジするなら歓迎)、ただし必須ではない。模範解答 PJ は Action パターンを採用しているが、Basic 受講生が Controller 内で実装している場合も振る舞いが受け入れ条件を満たせば OK。

- **採用技術**: Eloquent + Controller(受講生判断で Action 分割可) + Policy + FormRequest + Blade(提供済み) + `DB::transaction`
- **設計判断**:
  1. **Controller 共通化**: 公開(受講生 / コーチ)と管理者モデレーションで **同一 Controller**(`QaThreadController` / `QaReplyController`)を使い、Policy + `@can` で出し分け。Route 名は `qa-board.*` と `admin.qa-board.*` の 2 系統を維持(運用上の URL 区別)。Blade も同一ファイルを `$isAdminContext = request()->routeIs('admin.*')` で動的化(`management/` ディレクトリは作らない)。これは CertifyLMS の確立パターン(`backend-http.md` 「ロール別 namespace 禁止」と整合)
  2. **状態整合性**: `status` Enum + `resolved_at` datetime を同時更新で「status=resolved ⇔ resolved_at != null」担保(他 Feature の `Enrollment.status + passed_at` と整合)
  3. **列挙攻撃防御**: コーチ担当外資格 ID 指定時は 404 ではなく 403(資格存在は隠蔽しない)。一方、公開停止資格は受講生・コーチに 404(資格自体が見えない = リソース存在しない扱い)、admin は引き続き閲覧可
  4. **XSS 防御**: `{!! nl2br(e($body)) !!}` パターン、Markdown レンダリングしない
  5. **検索**: `LIKE` のみ(FULLTEXT INDEX / 外部全文検索エンジン不採用)、検索対象は `QaThread.title` / `body` / `QaReply.body` の OR 部分一致
  6. **削除戦略**: 物理削除を採用(SoftDelete 不採用)。スレッドのモデレーション削除時は配下回答を Action 内で連動削除する(`$thread->replies()->delete(); $thread->delete();`)。SoftDelete 不採用の根拠は規約 `backend-models.md` 「採用しないケース: 管理者画面に復旧 UX がない・会計監査要件がない」に従う
  7. **JS なし(純 Laravel)**: Basic チケットのため JS を使わない(JS は Advance スコープ)。操作メニュー(dropdown)は使わず編集リンク + 削除フォームを直接配置、回答編集はインライン編集でなく専用ページ(`qa-board.replies.edit`)へ遷移、削除確認は `onsubmit="return confirm()"`(HTML 標準で JS ファイル不要)。BookShelf / ContactForm の Basic パターン(編集=専用ページ / 削除=フォーム POST + confirm)に準拠。提供 PJ の Blade にはマークアップ構造を示すコメント(各ブロックの役割)とフロント実装観点(JS なし / confirm / XSS 表示処理)のみ付与し、**バックエンド設計(Controller / Action / Policy / route 定義 / Eager Load / バリデーション)には一切触れない** — 受講生が Blade を読んで必要なバックエンドを自力で設計する余地を残すため

### 関連ファイルメモ

- `app/Models/QaThread.php` / `app/Models/QaReply.php`
- `app/Enums/QaThreadStatus.php`
- `app/Http/Controllers/QaThreadController.php` / `app/Http/Controllers/QaReplyController.php`(受講生 / コーチ / admin 共通、Policy + `@can` で出し分け)
- `app/UseCases/QaThread/{Index,Show,Store,Update,Destroy,Resolve,Unresolve}Action.php` / `app/UseCases/QaReply/{Store,Update,Destroy}Action.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/Policies/QaThreadPolicy.php` / `app/Policies/QaReplyPolicy.php`
- `app/Http/Requests/QaThread/{Index,Store,Update}Request.php` / `app/Http/Requests/QaReply/{Store,Update}Request.php`
- `app/Exceptions/QaBoard/{QaThreadHasReplies,QaThreadAlreadyResolved,QaThreadNotResolved}Exception.php`
- `resources/views/qa-thread/{index,show,create,edit,reply-edit}.blade.php` + `_thread-card` / `_filter` / `_reply` / `_reply-form` partials(`$isAdminContext` で route 動的化、JS なし・教育コメント付き)
- `database/migrations/*_create_qa_threads_table.php` / `database/migrations/*_create_qa_replies_table.php`

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| タイトル / 本文の最大文字数は? | タイトル 200 文字 / 本文 5000 文字 / 回答本文 5000 文字、全角空白のみの入力は拒否 |
| コーチが担当外資格のスレッドを直叩きしたら 403 / 404? | **403**(担当外であることを明示、資格存在は隠蔽しない) |
| 公開停止資格のスレッドにアクセスしたら 403 / 404? | 受講生・コーチには **404**(資格自体が見えない = リソース存在しない扱い)、管理者は引き続き閲覧可 |
| フラッシュメッセージの推奨文言は? | 投稿「質問を投稿しました」/ 編集「質問を更新しました」/ 削除「質問を削除しました」/ 削除エラー「回答が付いているスレッドは削除できません。」/ 回答投稿「回答を投稿しました」/ 解決「解決済にしました」/ 解除「未解決に戻しました」(適切な日本語であれば文言の細部は採点対象外) |
| スレッド削除条件は? | 投稿者本人 × 回答 0 件のみ。管理者は無条件(配下回答も連動削除) |
| 解決マーク / 解除は管理者も代行できる? | いいえ、投稿者本人のみ(管理者であっても代行不可) |
| 削除は SoftDelete / 物理削除? | 物理削除(履歴保持なし)。SoftDelete は採用しない |
| admin と一般ユーザーで Controller / Blade は分離されている? | いいえ、同一 Controller / Blade を使い、Route 名と `@can` で出し分け。admin 専用画面(`/admin/qa-board`)は同じ Blade を `$isAdminContext = request()->routeIs('admin.*')` で動的に切替表示 |
| キーワード検索の対象範囲は? | 質問スレッドのタイトル / 本文 / 回答本文の OR 部分一致(完全一致ではなく部分一致) |
| ページネーションは何件 / ページ? | 20 件(回答はページネーションなし、詳細画面で全件表示) |
| 管理者はスレッド / 回答の内容編集できる? | いいえ、閲覧 + 削除のみ(モデレーション削除のみ可、内容の上書きは不可) |
| 学習有効性ガード(受講停止状態のブロック)の影響は? | 全受講生 / コーチの全画面に適用される(修了済受講生をブロック、コーチは影響なし) |
| 同時編集競合の楽観ロックは? | 持たない(教育 PJ スコープ外) |
