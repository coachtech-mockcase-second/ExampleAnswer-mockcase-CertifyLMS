# meeting-quota 実装タスク

> **v3 Blocker 解消(Phase D / F)**:
> - D-1: `GrantInitialQuotaAction` 統一シグネチャ確立(本 Feature 所有)
> - D-3: Stripe Webhook 冪等性 5 ステップ + `MeetingQuotaService::history` 詳細設計
> - D-4: Controller / FormRequest / Policy / Route の API 契約は design.md で明示
> - **N1(Phase F): Stripe Webhook URL を `/webhooks/stripe` に統一**(requirements / design / tasks 全て揃った)
> - **M1(Phase F): 実装着手時に各タスクへ REQ ID 注記**(`REQ-meeting-quota-NNN` を行末に `(REQ-NNN)` 形式で追加)
> - **N6(Phase F): `meeting_quota_transactions.related_meeting_id` の FK 制約は別 Migration で追加**(mentoring の `meetings` テーブル作成後)
> - **M4(Phase F): `remaining(User)` formula は requirements REQ-041 の一行 formula を正本**

## Phase 1: 依存パッケージ

- [x] `composer require stripe/stripe-php`
- [x] `.env.example` に `STRIPE_SECRET_KEY=` / `STRIPE_PUBLISHABLE_KEY=` / `STRIPE_WEBHOOK_SECRET=` を追加
- [x] `config/services.php` に `stripe` セクションを追加(`secret` / `publishable_key` / `webhook_secret`)

## Phase 2: マイグレーション

- [x] `database/migrations/{ts}_create_meeting_quota_plans_table.php`(ULID + SoftDeletes + name + description + meeting_count + price + stripe_price_id nullable + status enum + sort_order + created_by + updated_by + INDEX (status, sort_order))
- [x] `database/migrations/{ts}_create_meeting_quota_transactions_table.php`(ULID + `user_id` FK restrict + `type` enum 5 値(granted_initial / purchased / consumed / refunded / admin_grant) + `amount` int signed + **`related_meeting_id` ULID nullable**(FK 制約は本 Migration では付けない、mentoring の `meetings` テーブル未作成時の依存回避) + `related_payment_id` FK nullable to `payments`(同一 Feature) + `granted_by_user_id` FK nullable to `users` + `note` varchar:500 + `occurred_at` datetime + INDEX (user_id, occurred_at) + (related_meeting_id) + (related_payment_id) + (type)、SoftDelete 不採用)
- [x] `database/migrations/{ts}_add_related_meeting_fk_to_meeting_quota_transactions.php`(**N6**: mentoring の `meetings` テーブル作成後に別 Migration で `related_meeting_id` に FK 制約追加、`restrictOnDelete`、mentoring Step 1 完了後に走らせる)
- [x] `database/migrations/{ts}_create_payments_table.php`(ULID + SoftDeletes + `user_id` FK + `type` enum + `meeting_quota_plan_id` FK restrict + `stripe_payment_intent_id` UNIQUE nullable + `stripe_checkout_session_id` UNIQUE NOT NULL + `amount` unsigned + `quantity` unsigned smallint + `status` enum 4 値(pending / succeeded / failed / refunded) + `paid_at` nullable + INDEX (user_id) + (status, paid_at))
- [x] `users.max_meetings` カラム追加は [[plan-management]] の Migration に合流

## Phase 3: Enum / Model

- [x] `app/Enums/MeetingQuotaPlanStatus.php`(`Draft` / `Published` / `Archived` + `label()`)
- [x] `app/Enums/MeetingQuotaTransactionType.php`(`GrantedInitial` / `Purchased` / `Consumed` / `Refunded` / `AdminGrant` + `label()`)
- [x] `app/Enums/PaymentStatus.php`(`Pending` / `Succeeded` / `Failed` / `Refunded` + `label()`)
- [x] `app/Models/MeetingQuotaPlan.php`(`HasUlids` + `HasFactory` + `SoftDeletes` + fillable + `$casts['status'=>MeetingQuotaPlanStatus, 'meeting_count'=>'integer', 'price'=>'integer', 'sort_order'=>'integer']` + `belongsTo(User, createdBy)` + `belongsTo(User, updatedBy)` + `hasMany(Payment)` + `scopePublished` / `scopeOrdered`)
- [x] `app/Models/MeetingQuotaTransaction.php`(`HasUlids` + `HasFactory`、SoftDelete 不採用 + fillable + `$casts['type'=>MeetingQuotaTransactionType, 'amount'=>'integer', 'occurred_at'=>'datetime']` + **`belongsTo(User)`** + **`belongsTo(Meeting, related_meeting_id, relatedMeeting)`** + **`belongsTo(Payment, related_payment_id, relatedPayment)`** + **`belongsTo(User, granted_by_user_id, grantedBy)`**(D-3 で history 用 Eager Loading に必要))
- [x] `app/Models/Payment.php`(`HasUlids` + `HasFactory` + `SoftDeletes` + fillable + `$casts['status'=>PaymentStatus, 'amount'=>'integer', 'quantity'=>'integer', 'paid_at'=>'datetime']` + `belongsTo(User)` + `belongsTo(MeetingQuotaPlan)` + `hasMany(MeetingQuotaTransaction, related_payment_id)`)
- [x] `app/Models/User.php` 拡張: `hasMany(MeetingQuotaTransaction)` 追加

## Phase 4: Policy / Middleware

- [x] `app/Policies/MeetingQuotaPlanPolicy.php`(admin only、viewAny / view / create / update / delete / publish / archive / unarchive)
- [x] `app/Policies/MeetingQuotaPolicy.php`(受講生向け、`purchase(User)` / `viewHistory(User auth, User target)`)
- [x] `app/Http/Middleware/VerifyStripeSignature.php`(`Stripe\Webhook::constructEvent` で署名検証 + 失敗時 `StripeWebhookSignatureInvalidException` throw + 成功時 `$request->merge(['stripe_event' => ...])`)
- [x] `app/Http/Kernel.php` の `$middlewareAliases` に `'stripe.signature' => VerifyStripeSignature::class` 登録
- [x] **`app/Http/Middleware/VerifyCsrfToken::$except`** に `/webhooks/stripe` を追加(CSRF 除外)

## Phase 5: ドメイン例外

- [x] `app/Exceptions/MeetingQuota/InsufficientMeetingQuotaException.php`(409)
- [x] `app/Exceptions/MeetingQuota/MeetingQuotaPlanNotDeletableException.php`(409)
- [x] `app/Exceptions/MeetingQuota/MeetingQuotaPlanNotPublishedException.php`(422)
- [x] `app/Exceptions/MeetingQuota/MeetingQuotaPlanInvalidTransitionException.php`(409)
- [x] `app/Exceptions/MeetingQuota/UserNotInProgressException.php`(403)
- [x] `app/Exceptions/MeetingQuota/StripeWebhookSignatureInvalidException.php`(400)

## Phase 6: FormRequest

- [x] `app/Http/Requests/MeetingQuotaPlan/StoreRequest.php`(`name` / `description` / `meeting_count: 1..100` / `price: 0..1000000` / `sort_order`)
- [x] `app/Http/Requests/MeetingQuotaPlan/UpdateRequest.php`(同 rules)
- [x] `app/Http/Requests/MeetingQuotaPlan/IndexRequest.php`(`status` / `keyword` 任意フィルタ)
- [x] `app/Http/Requests/MeetingQuota/CheckoutRequest.php`(`meeting_quota_plan_id: required ulid + exists where status=published`)
- [x] `app/Http/Requests/MeetingQuota/HistoryIndexRequest.php`(`type` 任意フィルタ + `page`)

> **`AdminGrantQuotaRequest` は [[user-management]] 所有**(本 Feature では `AdminGrantQuotaAction` のみ提供)

## Phase 7: Service

- [x] **`app/Services/MeetingQuotaService.php`(D-3 で `history` 詳細設計)**
  - `remaining(User $user): int` — `max_meetings + SUM(consumed + refunded + purchased + admin_grant)`(`granted_initial` 除外、二重カウント防止)
  - **`history(User $user, ?MeetingQuotaTransactionType $type = null, int $perPage = 20): LengthAwarePaginator`** — `with(['relatedMeeting.enrollment.certification', 'relatedPayment.meetingQuotaPlan', 'grantedBy'])` Eager Loading + `when($type, ...)` フィルタ + `orderByDesc('occurred_at')` + paginate

## Phase 8: UseCase / Action

### Plan CRUD

- [x] `app/UseCases/MeetingQuotaPlan/IndexAction.php` / `ShowAction.php` / `StoreAction.php` / `UpdateAction.php` / `DestroyAction.php`(`participants` 確認、参照中で 409)
- [x] `app/UseCases/MeetingQuotaPlan/PublishAction.php` / `ArchiveAction.php` / `UnarchiveAction.php`

### Quota Actions(D-1 で統一シグネチャ)

- [x] **`app/UseCases/MeetingQuota/GrantInitialQuotaAction.php`(D-1)** — `__invoke(User $user, int $amount, ?User $admin = null, ?string $reason = null): MeetingQuotaTransaction`、`amount > 0` 検証 + `granted_by_user_id = $admin?->id` で INSERT、本 Feature 所有(auth `OnboardAction` + plan-management `ExtendCourseAction` から呼ばれる)
- [x] `app/UseCases/MeetingQuota/ConsumeQuotaAction.php`(`__invoke(User $user, string $meetingId): MeetingQuotaTransaction`、`DB::transaction` + `User::lockForUpdate` で同時消費 race を直列化、残数 1 以上検証 + `amount=-1` INSERT。`Meeting` Model は mentoring 所有のため ULID 文字列で受け取る)
- [x] `app/UseCases/MeetingQuota/RefundQuotaAction.php`(`__invoke(User $user, string $meetingId): MeetingQuotaTransaction`、`DB::transaction` 内で `amount=+1` INSERT)
- [x] `app/UseCases/MeetingQuota/AdminGrantQuotaAction.php`(`__invoke(User $target, int $amount, User $admin, ?string $reason = null): MeetingQuotaTransaction`、`granted_by_user_id = $admin->id` 必須)
- [x] `app/UseCases/MeetingQuota/PurchaseQuotaAction.php`(`__invoke(Payment): MeetingQuotaTransaction`、Webhook 内部から呼ばれる、`amount = payment.quantity` INSERT)

### Stripe Actions

- [x] `app/UseCases/MeetingQuota/CreateCheckoutSessionAction.php`(`__invoke(User, MeetingQuotaPlan): array`、Stripe Checkout Session 作成 + Payment pending INSERT)
- [x] **`app/UseCases/MeetingQuota/StripeWebhook\HandleAction.php`(D-3 で 5 ステップ明示)**
  - `__invoke(array $event): void` シグネチャ
  - `match ($event['type'])` で 3 イベント分岐: `checkout.session.completed` / `checkout.session.expired` / `payment_intent.payment_failed`
  - `handleCheckoutCompleted` の **5 ステップ**: (1) `lockForUpdate` で Payment SELECT → (2) 未存在 skip → (3) 既 succeeded skip(冪等性) → (4) Payment status UPDATE + stripe_payment_intent_id / paid_at セット → (5) PurchaseQuotaAction 呼出
  - すべて `DB::transaction()` 内で実行

## Phase 9: Controller

- [x] `app/Http/Controllers/Admin/MeetingQuotaPlanController.php`(`index` / `create` / `store` / `show` / `edit` / `update` / `destroy`)
- [x] `app/Http/Controllers/Admin/MeetingQuotaPlanStatusController.php`(`publish` / `archive` / `unarchive`)
- [x] `app/Http/Controllers/MeetingQuota/CheckoutController.php`(`select` Blade描画 / `create(CheckoutRequest, CreateCheckoutSessionAction)` / `success` 決済完了画面)
- [x] `app/Http/Controllers/MeetingQuota/HistoryController.php`(`index(HistoryIndexRequest)` → `MeetingQuotaService::history` 呼出)
- [x] `app/Http/Controllers/Webhooks/StripeWebhookController.php`(`handle(Request $request, StripeWebhook\HandleAction $action)`、`$request->input('stripe_event')` を Action に渡す)

> **`Admin\UserController::grantMeetingQuota` は [[user-management]] 所有**(本 Feature の `AdminGrantQuotaAction` を DI で呼ぶ)

## Phase 10: Route

- [x] `routes/web.php`:
  - **admin**(`auth + role:admin` group + `prefix('admin')`):
    - `Route::resource('meeting-quota-plans', Admin\MeetingQuotaPlanController::class)`
    - `Route::post('meeting-quota-plans/{plan}/publish'|'archive'|'unarchive', Admin\MeetingQuotaPlanStatusController::class, ...)`
  - **student**(`auth + role:student + EnsureActiveLearning` group + `prefix('meeting-quota')`):
    - `Route::get('checkout', CheckoutController::select)`
    - `Route::post('checkout', CheckoutController::create)`
    - `Route::get('success', CheckoutController::success)`
    - `Route::get('history', HistoryController::index)`
  - **Webhook**(認証なし、署名検証のみ):
    - `Route::post('webhooks/stripe', Webhooks\StripeWebhookController::handle)->middleware('stripe.signature')->name('webhooks.stripe')`
- [x] `app/Http/Middleware/VerifyCsrfToken::$except` に `/webhooks/stripe` 追加

## Phase 11: Blade

- [x] `resources/views/admin/meeting-quota-plans/index.blade.php`(SKU 一覧)
- [x] `resources/views/admin/meeting-quota-plans/create.blade.php` / `edit.blade.php`(フォーム)
- [x] `resources/views/admin/meeting-quota-plans/show.blade.php`(詳細 + 購入履歴)
- [x] `resources/views/meeting-quota/checkout-select.blade.php`(受講生 SKU 選択、dashboard プラン情報パネルから遷移)
- [x] `resources/views/meeting-quota/success.blade.php`
- [x] `resources/views/meeting-quota/history.blade.php`(受講生用、type フィルタ + paginate + 各 transaction の関連 Meeting / Payment / admin 名表示)

> `resources/views/admin/users/_modals/grant-meeting-quota.blade.php` は [[user-management]] 所有

## Phase 12: Test

### Plan CRUD

- [x] `tests/Feature/Http/Admin/MeetingQuotaPlanControllerTest.php`(CRUD + 状態遷移 + 認可)

### Checkout / Webhook

- [x] `tests/Feature/Http/MeetingQuota/CheckoutControllerTest.php`(Stripe Checkout Session 作成 + Payment INSERT + `Http::fake` でモック)
- [x] **`tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php`(D-3)** — 署名検証 + `checkout.session.completed` で Payment succeeded + MeetingQuotaTransaction(purchased) INSERT、**5 ステップ網羅**: (1) Payment 未存在で何もしない / (2) 既 succeeded → 二重処理なし(冪等性、同イベント 2 回受信で transaction 1 件のみ) / (3) pending → succeeded UPDATE / (4) `checkout.session.expired` で pending → failed / (5) `payment_intent.payment_failed` で failed

### Quota Actions(D-1)

- [x] **`tests/Feature/UseCases/MeetingQuota/GrantInitialQuotaActionTest.php`(D-1)**:
  - `($user, 5)` で `granted_by_user_id = NULL` で INSERT
  - `($user, 5, $admin)` で `granted_by_user_id = $admin->id` で INSERT
  - `($user, 5, $admin, '理由')` で `note = '理由'` で INSERT
  - `($user, 0)` で `InvalidArgumentException`
  - `($user, -1)` で `InvalidArgumentException`
- [x] `tests/Feature/UseCases/MeetingQuota/ConsumeQuotaActionTest.php`(残数 1 以上で消費成功 / 0 で 409)
- [x] `tests/Feature/UseCases/MeetingQuota/RefundQuotaActionTest.php`(refund で +1 INSERT)
- [x] `tests/Feature/UseCases/MeetingQuota/AdminGrantQuotaActionTest.php`(`granted_by_user_id = $admin->id` 必須)
- [x] `tests/Feature/UseCases/MeetingQuota/PurchaseQuotaActionTest.php`(Payment.quantity を amount として INSERT)

### Service(D-3)

- [x] **`tests/Unit/Services/MeetingQuotaServiceTest.php`**:
  - **`remaining`**: max_meetings + 各種 transaction の合算(granted_initial 除外、二重カウント防止確認)
  - **`history`(D-3)**: type フィルタ動作 / Eager Loading で N+1 なし(`DB::enableQueryLog` でクエリ数検証) / `orderByDesc('occurred_at')` / paginate 20 件

## Phase 13: ファクトリ + シーダー

- [x] `database/factories/MeetingQuotaPlanFactory.php`(`published()` / `draft()` / `archived()` state、`withCount(int)` / `withPrice(int)` state)
- [x] `database/factories/MeetingQuotaTransactionFactory.php`(各 type state: `grantedInitial()` / `purchased()` / `consumed()` / `refunded()` / `adminGrant(User $admin)`)
- [x] `database/factories/PaymentFactory.php`(各 status state)
- [x] `database/seeders/MeetingQuotaPlanSeeder.php`(開発用、例: 1 回 ¥3,000 / 5 回パック ¥12,000 / 10 回パック ¥22,000)
