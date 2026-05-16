# qa-board タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-qa-board-NNN` / `NFR-qa-board-NNN` を参照。
> コマンドはすべて Sail プレフィックス（`sail artisan ...` / `sail npm ...` / `sail bin pint`）で実行する（`tech.md`「コマンド慣習」参照）。

## Step 1: Migration & Model

- [ ] Enum: `app/Enums/QaThreadStatus.php`（backed string、`Open = 'open'` / `Resolved = 'resolved'`、`label()` で `未解決` / `解決済` を返す）（REQ-qa-board-004）
- [ ] migration: `create_qa_threads_table`（ULID 主キー、SoftDeletes、`certification_id` FK [restrict] + INDEX、`user_id` FK [restrict] + INDEX、`title` VARCHAR(200)、`body` TEXT、`status` string NOT NULL default 'open'、`resolved_at` nullable datetime、`(certification_id, status)` 複合 INDEX、`deleted_at` INDEX）（REQ-qa-board-001, REQ-qa-board-002, REQ-qa-board-003）
- [ ] migration: `create_qa_replies_table`（ULID 主キー、SoftDeletes、`qa_thread_id` FK [restrict]、`user_id` FK [restrict]、`body` TEXT、`(qa_thread_id, created_at)` 複合 INDEX、`user_id` INDEX、`deleted_at` INDEX）（REQ-qa-board-010, REQ-qa-board-011, REQ-qa-board-012）
- [ ] Model: `QaThread`（`HasUlids` + `SoftDeletes` + `HasFactory`、fillable、`$casts` で `status => QaThreadStatus::class` / `resolved_at => 'datetime'`、`belongsTo(Certification)` / `belongsTo(User, 'user_id')` / `hasMany(QaReply)`、`scopeResolved()`（`status = Resolved`）/ `scopeUnresolved()`（`status = Open`）/ `scopeForCertification($id)`、`isResolved(): bool` ヘルパ）（REQ-qa-board-004, REQ-qa-board-005, REQ-qa-board-006）
- [ ] Model: `QaReply`（`HasUlids` + `SoftDeletes` + `HasFactory`、fillable、`belongsTo(QaThread)` / `belongsTo(User, 'user_id')`）（REQ-qa-board-013, REQ-qa-board-014）
- [ ] Factory: `QaThreadFactory`（`resolved()`（`status=Resolved` + `resolved_at=now()`）/ `unresolved()`（`status=Open` + `resolved_at=null`）state、`forCertification($cert)` / `byUser($user)` ヘルパ）
- [ ] Factory: `QaReplyFactory`（`forThread($thread)` / `byUser($user)` ヘルパ）
- [ ] `sail artisan migrate:fresh --seed` で migration 成功確認

## Step 2: Policy

- [ ] `QaThreadPolicy`（`viewAny` / `view` / `create` / `update` / `delete` / `resolve` / `unresolve`、ロール + 当事者 + 担当資格の三重判定）（NFR-qa-board-004, NFR-qa-board-006）
- [ ] `QaReplyPolicy`（`create` / `update` / `delete`、admin は `create` で false）（NFR-qa-board-004）
- [ ] `AuthServiceProvider` への登録確認（Laravel 自動検出に依存する場合はモデル名規約を確認）
- [ ] `tests/Unit/Policies/QaThreadPolicyTest.php`（admin / coach 担当内 / coach 担当外 / student / 他 student × 各メソッドの真偽値網羅）
- [ ] `tests/Unit/Policies/QaReplyPolicyTest.php`（同上、admin の `create` が false になることを確認）

## Step 3: HTTP 層

- [ ] `QaThreadController`（index / show / create / store / edit / update / destroy / resolve / unresolve、Controller 内のロジックは 0 行、Action 委譲のみ）（REQ-qa-board-020〜054, REQ-qa-board-090〜094）
- [ ] `QaReplyController`（store / update / destroy）（REQ-qa-board-060〜084）
- [ ] `Admin\QaThreadController`（index / show / destroy、`role:admin` Middleware 適用）（REQ-qa-board-130〜132）
- [ ] `Admin\QaReplyController`（destroy）（REQ-qa-board-081）
- [ ] `app/Http/Requests/QaThread/IndexRequest`（`certification_id` / `status` / `keyword` / `page` バリデーション）（REQ-qa-board-100〜105）
- [ ] `app/Http/Requests/QaThread/StoreRequest`（`certification_id` Rule::exists + status=published、`title` / `body` max + 全角空白拒否）（REQ-qa-board-022〜024）
- [ ] `app/Http/Requests/QaThread/UpdateRequest`（`title` / `body` バリデーション、`authorize()` で Policy 呼出）（REQ-qa-board-040, REQ-qa-board-043）
- [ ] `app/Http/Requests/QaReply/StoreRequest`（`body` バリデーション、`authorize()` で `[QaReply::class, $thread]` Policy 呼出）（REQ-qa-board-061〜063）
- [ ] `app/Http/Requests/QaReply/UpdateRequest`（`body` バリデーション、`authorize()` で Policy 呼出）（REQ-qa-board-070, REQ-qa-board-072）
- [ ] `app/Http/Requests/Admin/QaThread/IndexRequest`（`with_trashed` bool 追加、admin ロール `authorize()`）（REQ-qa-board-130）
- [ ] `routes/web.php` への登録（公開 9 ルート + replies 3 ルート + admin 4 ルート、**student/coach 用ルートには `EnsureActiveLearning` Middleware 追加**（v3、graduated 受講生をブロック）、admin ルートには適用しない、`withTrashed()` ルートバインディングを admin show / destroy に適用）（REQ-qa-board-030, REQ-qa-board-131）

## Step 4: Action / Exception

- [ ] `app/UseCases/QaThread/IndexAction`（`User $viewer, array $filters` 受け取り、ロール分岐 + フィルタ + 部分一致 LIKE + Eager Loading + 20 件ページネーション）（REQ-qa-board-030〜037, REQ-qa-board-100〜105, NFR-qa-board-002）
- [ ] `app/UseCases/QaThread/ShowAction`（`QaThread $thread` の `with(['certification', 'user', 'replies.user'])` Eager Loading）（REQ-qa-board-035, REQ-qa-board-037）
- [ ] `app/UseCases/QaThread/StoreAction`（`DB::transaction` で `qa_threads` INSERT、`status = QaThreadStatus::Open` / `resolved_at = null` 初期値）（REQ-qa-board-020）
- [ ] `app/UseCases/QaThread/UpdateAction`（`title` / `body` のみ UPDATE）（REQ-qa-board-040, REQ-qa-board-044）
- [ ] `app/UseCases/QaThread/DestroyAction`（回答 0 件チェック + SoftDelete、`QaThreadHasRepliesException` throw）（REQ-qa-board-050, REQ-qa-board-051, REQ-qa-board-054）
- [ ] `app/UseCases/QaThread/ResolveAction`（`QaThreadAlreadyResolvedException` ガード + `status = Resolved` / `resolved_at = now()` の同時 UPDATE）（REQ-qa-board-006, REQ-qa-board-090, REQ-qa-board-093）
- [ ] `app/UseCases/QaThread/UnresolveAction`（`QaThreadNotResolvedException` ガード + `status = Open` / `resolved_at = null` の同時 UPDATE）（REQ-qa-board-006, REQ-qa-board-091, REQ-qa-board-094）
- [ ] `app/UseCases/QaReply/StoreAction`（`DB::transaction` 内で INSERT + 自己回答以外なら **`QaReplyReceivedNotification`**（v3 rename）通知 dispatch）（REQ-qa-board-064, REQ-qa-board-065）
- [ ] `app/UseCases/QaReply/UpdateAction`（`body` のみ UPDATE）（REQ-qa-board-070, REQ-qa-board-073）
- [ ] `app/UseCases/QaReply/DestroyAction`（SoftDelete、スレッド状態不変）（REQ-qa-board-080, REQ-qa-board-083）
- [ ] `app/UseCases/AdminQaThread/IndexAction`（全資格・全状態・SoftDelete 含む切替で 20 件ページネーション）（REQ-qa-board-130）
- [ ] `app/UseCases/AdminQaThread/ShowAction`（`withTrashedReplies` フラグで SoftDelete 済回答も含めて Eager Load）（REQ-qa-board-131）
- [ ] `app/UseCases/AdminQaThread/DestroyAction`（回答有無不問で SoftDelete）（REQ-qa-board-052, REQ-qa-board-132）
- [ ] `app/UseCases/AdminQaReply/DestroyAction`（SoftDelete のみ）（REQ-qa-board-081, REQ-qa-board-132）
- [ ] ドメイン例外: `app/Exceptions/QaBoard/QaThreadHasRepliesException` extends `ConflictHttpException`（NFR-qa-board-003）
- [ ] ドメイン例外: `app/Exceptions/QaBoard/QaThreadAlreadyResolvedException` extends `ConflictHttpException`（NFR-qa-board-003）
- [ ] ドメイン例外: `app/Exceptions/QaBoard/QaThreadNotResolvedException` extends `ConflictHttpException`（NFR-qa-board-003）

## Step 5: Notification（[[notification]] 所有、本 Feature では起点呼出のみ）

- [ ] `App\UseCases\QaReply\StoreAction` 内で `app(NotifyQaReplyReceivedAction::class)($reply)` を呼び出し（自己回答ガード + 受信者解決 + Notification dispatch は [[notification]] 所有のラッパー側で処理）（REQ-qa-board-110, REQ-qa-board-111）
- [ ] Notification クラス本体（`App\Notifications\QaReplyReceivedNotification`）/ Mail テンプレ / `toDatabase` ペイロード設計は **[[notification]] spec で確定**（REQ-notification-040, REQ-notification-042, REQ-notification-043 を参照）。本 Feature では Notification クラスを新設しない

## Step 6: Blade ビュー

- [ ] `resources/views/qa-board/index.blade.php`（フィルタフォーム + スレッド一覧 + ページネーション + 新規投稿ボタン、共通コンポーネント利用）（REQ-qa-board-030, REQ-qa-board-031, REQ-qa-board-036）
- [ ] `resources/views/qa-board/create.blade.php`（`<x-form.select>` 資格選択 + `<x-form.input>` タイトル + `<x-form.textarea maxlength=5000>` 本文）（REQ-qa-board-020, REQ-qa-board-026）
- [ ] `resources/views/qa-board/edit.blade.php`（タイトル + 本文編集フォーム、資格は読み取り専用表示）（REQ-qa-board-040）
- [ ] `resources/views/qa-board/show.blade.php`（質問本文 + 解決マーク / 解除ボタン + 編集 / 削除メニュー + 回答一覧 + 回答投稿フォーム、`{!! nl2br(e($thread->body)) !!}` パターン徹底）（REQ-qa-board-033〜035, NFR-qa-board-005）
- [ ] `resources/views/qa-board/_thread-card.blade.php`（一覧 1 行 partial、`<x-badge>` で資格・解決状態・回答件数表示）
- [ ] `resources/views/qa-board/_reply.blade.php`（回答 1 件 partial、編集 / 削除メニューを `@can` でガード）（REQ-qa-board-070, REQ-qa-board-080）
- [ ] `resources/views/qa-board/_reply-form.blade.php`（回答投稿フォーム partial、@can でガード）（REQ-qa-board-060〜063）
- [ ] `resources/views/qa-board/_filter.blade.php`（資格 select + 解決状態 radio + keyword input、`withQueryString` 引継ぎ）（REQ-qa-board-100〜103, REQ-qa-board-105）
- [ ] `resources/views/admin/qa-board/index.blade.php`（admin 用全スレッド一覧 + with_trashed トグル + モデレーション削除導線）（REQ-qa-board-130）
- [ ] `resources/views/admin/qa-board/show.blade.php`（SoftDelete 済回答含む詳細 + 削除ボタン）（REQ-qa-board-131, REQ-qa-board-132）

## Step 7: SidebarBadgeComposer 拡張

- [ ] `app/View/Composers/SidebarBadgeComposer` の coach 分岐に「担当資格 × 未回答スレッド数」集計を追加（`whereIn coachingCertificationIds` + `where('status', QaThreadStatus::Open)` + `whereDoesntHave replies`、1 クエリ）（REQ-qa-board-120, REQ-qa-board-122）
- [ ] `resources/views/layouts/_partials/sidebar-coach.blade.php` の `<x-nav.item route="coach.qa-board.index" ... :badge="$sidebarBadges['unanswered_qa']">` 連動確認（REQ-qa-board-120）
- [ ] `resources/views/layouts/_partials/sidebar-student.blade.php` の `<x-nav.item route="qa-board.index" ...>` にバッジを設定しないことを確認（REQ-qa-board-121）

## Step 8: テスト

- [ ] `tests/Feature/Http/QaThread/IndexTest`（student で全公開資格スレッド閲覧 / coach で担当資格のみ / 担当外資格 ID 指定 → 403 / 各フィルタ正常系 / N+1 検知）（REQ-qa-board-030, REQ-qa-board-031, REQ-qa-board-100〜103, NFR-qa-board-002, NFR-qa-board-006）
- [ ] `tests/Feature/Http/QaThread/ShowTest`（student で公開資格スレッド閲覧 / coach 担当外で 403 / SoftDelete 済スレッドで 404 / 未公開資格スレッドで 404）（REQ-qa-board-033, REQ-qa-board-034）
- [ ] `tests/Feature/Http/QaThread/StoreTest`（student で投稿成功 / coach 投稿で 403 / admin 投稿で 403 / 未公開資格指定で 422 / title 全角空白のみで 422 / body max 超過で 422）（REQ-qa-board-020〜025）
- [ ] `tests/Feature/Http/QaThread/UpdateTest`（投稿者本人で編集成功 / 他 student / coach / admin で 403 / 解決済スレッドの編集が可能であること）（REQ-qa-board-040〜042）
- [ ] `tests/Feature/Http/QaThread/DestroyTest`（投稿者本人 × 回答 0 件で削除成功 / 投稿者本人 × 回答ありで 409 / 投稿者本人 × SoftDelete 済回答のみありでも 409 / admin で削除成功 / coach / 他 student で 403）（REQ-qa-board-050〜054, REQ-qa-board-084）
- [ ] `tests/Feature/Http/QaThread/ResolveTest`（投稿者本人で resolve / 解除 / 重複 resolve で 409 / 未解決 unresolve で 409 / 他者で 403）（REQ-qa-board-090〜094）
- [ ] `tests/Feature/Http/QaReply/StoreTest`（student 公開資格で成功 / coach 担当資格で成功 / coach 担当外で 403 / admin で 403 / body 不正で 422 / 自己回答時に通知が dispatch されないこと / 他者回答時に通知が dispatch されること）（REQ-qa-board-060〜065, REQ-qa-board-110）
- [ ] `tests/Feature/Http/QaReply/UpdateTest`（投稿者本人で成功 / 他者で 403 / body 不正で 422）（REQ-qa-board-070〜072）
- [ ] `tests/Feature/Http/QaReply/DestroyTest`（投稿者本人で SoftDelete 成功 / admin で SoftDelete 成功 / 他者で 403 / スレッド `status` / `resolved_at` が変わらないことを assertDatabaseHas で確認）（REQ-qa-board-080〜083）
- [ ] `tests/Feature/Http/Admin/QaThread/IndexTest`（admin で全スレッド閲覧 / 非 admin で 403 / `with_trashed=1` で SoftDelete 含む）（REQ-qa-board-130, REQ-qa-board-132）
- [ ] `tests/Feature/Http/Admin/QaThread/DestroyTest`（admin で回答ありスレッド削除成功 / 非 admin で 403）（REQ-qa-board-052, REQ-qa-board-132）
- [ ] `tests/Feature/Http/Admin/QaReply/DestroyTest`（admin で削除成功 / スレッド `status` / `resolved_at` 不変）（REQ-qa-board-081, REQ-qa-board-083）
- [ ] `tests/Feature/UseCases/QaThread/DestroyActionTest`（回答 0 件で削除 / 回答ありで `QaThreadHasRepliesException` / SoftDelete 済回答のみでも例外）（REQ-qa-board-050, REQ-qa-board-051）
- [ ] `tests/Feature/UseCases/QaThread/ResolveActionTest` / `UnresolveActionTest`（状態遷移 + 重複時の例外）（REQ-qa-board-093, REQ-qa-board-094）
- [ ] `tests/Feature/UseCases/QaReply/StoreActionTest`（通知 dispatch 条件分岐 / 自己回答時に Notification::fake で 0 件確認）（REQ-qa-board-065, REQ-qa-board-110）
- [ ] `tests/Unit/Notifications/QaReplyReceivedNotificationTest`（[[notification]] 側で実装する Notification クラス本体のテスト。本 Feature では `tests/Feature/UseCases/QaReply/StoreActionTest` で `Notification::fake` + `assertSentTo` を使った dispatch 検証のみ実装）（REQ-qa-board-110〜113）
- [ ] `tests/Feature/SidebarBadgeComposerTest`（coach 担当資格未回答件数の集計確認 / student バッジ非表示 / 既に回答ありスレッドが集計から除外されること）（REQ-qa-board-120〜122）

## Step 9: 動作確認 & 整形

- [ ] `sail artisan test --filter=QaThread` / `--filter=QaReply` / `--filter=QaBoard` / `--filter=Admin\\\\QaThread` がすべて通過
- [ ] `sail bin pint --dirty` で整形
- [ ] ブラウザでの通しシナリオ確認:
  - student A がスレッド投稿 → 一覧 / 詳細で表示
  - student B が回答投稿 → student A に DB + Mail 通知（Mailpit `http://localhost:8025` で確認）
  - student A が解決マーク → 一覧で解決バッジ表示 / 解除 → 未解決バッジ
  - coach 担当外資格 ID をクエリで指定 → 403 ページ表示
  - admin で `/admin/qa-board` 開く → 全資格スレッド表示 / 回答ありスレッド削除成功
  - 投稿者本人による回答ありスレッド削除 → 409 のエラー表示
- [ ] サイドバーの coach 用「質問対応 (N)」バッジが未回答スレッド件数で更新されること
- [ ] 通知設定で `qa_reply_created` × `mail` を OFF にすると Mailpit に届かず、`database` のみ受信することを確認
