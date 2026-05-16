# meeting-quota 設計

> **v3 Blocker 解消(Phase D、2026-05-16)**:
> - **D-1: `GrantInitialQuotaAction::__invoke(User $user, int $amount, ?User $admin = null, ?string $reason = null): MeetingQuotaTransaction`** でシグネチャ統一(本 Feature が所有、plan-management の `ExtendCourseAction` および auth の `OnboardAction` から呼ばれる)
> - **D-3: `StripeWebhook\HandleAction` シグネチャ明示 + 冪等性 5 ステップ + `MeetingQuotaService::history` 詳細設計**
> - **D-4: Controller / FormRequest / Policy / Route の API 契約を表形式で明示**

## アーキテクチャ概要

```
Admin Browser                            Student Browser
    ↓                                        ↓
[Web Layer]                              [Web Layer]
MeetingQuotaPlanController              CheckoutController
                                        HistoryController
    ↓                                        ↓
[Policy / Middleware]                   [Policy / Middleware]
auth + role:admin                       auth + role:student + EnsureActiveLearning
    ↓                                        ↓
[UseCase 層]                            [UseCase 層]
StoreAction / UpdateAction              CreateCheckoutSessionAction
PublishAction / ArchiveAction
                                        ↓
                                        Stripe Checkout
                                        ↓
[Webhook]                               [Webhook]
POST /webhooks/stripe (no auth + VerifyStripeSignature)
    ↓
StripeWebhook\HandleAction
    ↓
[内部呼出されるグラント Action 群]
ConsumeQuotaAction (mentoring の Meeting\StoreAction から呼ばれる)
RefundQuotaAction (mentoring の Meeting\CancelAction から呼ばれる)
GrantInitialQuotaAction (auth の OnboardAction、plan-management の ExtendCourseAction から呼ばれる)
PurchaseQuotaAction (StripeWebhook\HandleAction の内部から呼ばれる)
AdminGrantQuotaAction (user-management の Admin\UserController::grantMeetingQuota から呼ばれる)
    ↓
[Service]
MeetingQuotaService::remaining / ::history
    ↓
[Model]
MeetingQuotaPlan / MeetingQuotaTransaction / Payment
```

## ERD

```
meeting_quota_plans
├ id ULID PK
├ name varchar(100) NOT NULL
├ description text NULL
├ meeting_count unsigned smallint NOT NULL (1..100)
├ price unsigned int NOT NULL (円、Stripe SKU として使用)
├ stripe_price_id varchar(255) NULL (将来の Stripe Price 連携用、現状未使用)
├ status enum('draft','published','archived')
├ sort_order unsigned int default 0
├ created_by_user_id / updated_by_user_id ULID FK restrict
├ created_at / updated_at / deleted_at
INDEX (status, sort_order), (deleted_at)

meeting_quota_transactions (履歴、INSERT only、SoftDelete 不採用)
├ id ULID PK
├ user_id ULID FK NOT NULL → users.id (restrict)
├ type enum('granted_initial','purchased','consumed','refunded','admin_grant')
├ amount int signed NOT NULL (消費は -1、その他は +N)
├ related_meeting_id ULID FK NULL → meetings.id (consumed / refunded)
├ related_payment_id ULID FK NULL → payments.id (purchased)
├ granted_by_user_id ULID FK NULL → users.id (admin_grant 時必須、それ以外 NULL 可)
├ note varchar(500) NULL (admin_grant の reason 等)
├ occurred_at datetime NOT NULL
├ created_at / updated_at
INDEX (user_id, occurred_at), (related_meeting_id), (related_payment_id), (type)

payments
├ id ULID PK
├ user_id ULID FK NOT NULL
├ type enum('extra_meeting_quota')
├ meeting_quota_plan_id ULID FK NOT NULL restrict
├ stripe_payment_intent_id varchar(255) UNIQUE NULL (succeeded 後にセット)
├ stripe_checkout_session_id varchar(255) UNIQUE NOT NULL
├ amount unsigned int NOT NULL (購入時 price)
├ quantity unsigned smallint NOT NULL (購入時 meeting_count)
├ status enum('pending','succeeded','failed','refunded')
├ paid_at datetime NULL
├ created_at / updated_at / deleted_at
INDEX (user_id), (status, paid_at), (deleted_at)
```

## 残数集計の整合性設計

**問題**: `User.max_meetings` と `MeetingQuotaTransaction.granted_initial` が二重カウントされる可能性。

**解決**: 以下のルールで一貫性を保つ。

| 操作 | `User.max_meetings` への影響 | `MeetingQuotaTransaction` への影響 |
|---|---|---|
| 招待時オンボーディング完了 ([[auth]] `OnboardAction`) | `= plan.default_meeting_quota` セット | `type=granted_initial`, `amount=+N` INSERT |
| `ExtendCourseAction` ([[plan-management]]) | `+= plan.default_meeting_quota` 加算 | `type=granted_initial`, `amount=+N` INSERT |
| `Meeting\StoreAction` ([[mentoring]]) | 変更なし | `type=consumed`, `amount=-1` INSERT |
| `Meeting\CancelAction` ([[mentoring]]) | 変更なし | `type=refunded`, `amount=+1` INSERT |
| Stripe 購入完了 (Webhook) | 変更なし | `type=purchased`, `amount=+N` INSERT |
| admin 手動付与 | 変更なし | `type=admin_grant`, `amount=+N` INSERT(`granted_by_user_id = $admin->id`) |

**残数計算式**:

```php
function remaining(User $user): int
{
    return $user->max_meetings + MeetingQuotaTransaction::where('user_id', $user->id)
        ->whereIn('type', [Consumed, Refunded, Purchased, AdminGrant])
        ->sum('amount');
    // 'granted_initial' は max_meetings と同期されているため除外
}
```

つまり `granted_initial` は **「max_meetings 変動の監査ログ」** として記録するが、残数計算には使わない。`max_meetings` カラム自体が初期 + 延長付与の累計を保持する。

## 主要 Action 設計

### GrantInitialQuotaAction(D-1 で本 Feature 所有 + 統一シグネチャ確立)

```php
namespace App\UseCases\MeetingQuota;

class GrantInitialQuotaAction
{
    /**
     * 初期付与 / プラン延長時の面談回数付与を記録する。
     * User.max_meetings の UPDATE は呼出側責務(plan-management の ExtendCourseAction 等)。
     * 本 Action は MeetingQuotaTransaction の INSERT のみを担う。
     *
     * @param User $user 対象
     * @param int $amount 付与回数(正の整数)
     * @param ?User $admin admin 経由の延長時に admin を渡す、初期付与時は NULL
     * @param ?string $reason 監査ログ用の理由文字列
     */
    public function __invoke(
        User $user,
        int $amount,
        ?User $admin = null,
        ?string $reason = null,
    ): MeetingQuotaTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Granted amount must be positive');
        }
        return MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::GrantedInitial,
            'amount' => $amount,
            'granted_by_user_id' => $admin?->id,  // D-1: admin 経由時は記録、システム自動時は NULL
            'note' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
```

> **D-1 で確立した呼出元**:
> - [[auth]] `OnboardAction`: `($user, $plan->default_meeting_quota, null, 'オンボーディング初期付与')`(admin=null、システム自動)
> - [[plan-management]] `ExtendCourseAction`: `($user, $plan->default_meeting_quota, $admin, 'プラン延長')`(admin=操作 admin、または null)

### ConsumeQuotaAction(mentoring の `Meeting\StoreAction` から呼ばれる)

```php
class ConsumeQuotaAction
{
    public function __construct(private MeetingQuotaService $service) {}

    public function __invoke(User $user, Meeting $meeting): MeetingQuotaTransaction
    {
        if ($this->service->remaining($user) < 1) {
            throw new InsufficientMeetingQuotaException();
        }
        return MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Consumed,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
            'occurred_at' => now(),
        ]);
    }
}
```

### RefundQuotaAction(mentoring の `Meeting\CancelAction` から呼ばれる)

```php
class RefundQuotaAction
{
    public function __invoke(User $user, Meeting $meeting): MeetingQuotaTransaction
    {
        return MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Refunded,
            'amount' => +1,
            'related_meeting_id' => $meeting->id,
            'occurred_at' => now(),
        ]);
    }
}
```

### AdminGrantQuotaAction(`.claude/rules/backend-usecases.md`「Feature 間連携のラッパー Action」規約に従い、[[user-management]] のラッパー `User\GrantMeetingQuotaAction` 経由で `Admin\UserController::grantMeetingQuota` から呼ばれる)

```php
class AdminGrantQuotaAction
{
    public function __invoke(User $target, int $amount, User $admin, ?string $reason = null): MeetingQuotaTransaction
    {
        if ($amount <= 0) throw new \InvalidArgumentException();
        return MeetingQuotaTransaction::create([
            'user_id' => $target->id,
            'type' => MeetingQuotaTransactionType::AdminGrant,
            'amount' => $amount,
            'granted_by_user_id' => $admin->id,  // 必須
            'note' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
```

### CreateCheckoutSessionAction(Stripe 連携)

```php
class CreateCheckoutSessionAction
{
    public function __invoke(User $user, MeetingQuotaPlan $plan): array
    {
        if ($plan->status !== MeetingQuotaPlanStatus::Published) {
            throw new MeetingQuotaPlanNotPublishedException();
        }
        if ($user->status !== UserStatus::InProgress) {
            throw new UserNotInProgressException();
        }

        return DB::transaction(function () use ($user, $plan) {
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $session = $stripe->checkout->sessions->create([
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'jpy',
                        'product_data' => ['name' => $plan->name],
                        'unit_amount' => $plan->price,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('meeting-quota.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('dashboard.index'),
                'metadata' => [
                    'user_id' => $user->id,
                    'meeting_quota_plan_id' => $plan->id,
                ],
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'type' => 'extra_meeting_quota',
                'meeting_quota_plan_id' => $plan->id,
                'stripe_checkout_session_id' => $session->id,
                'amount' => $plan->price,
                'quantity' => $plan->meeting_count,
                'status' => PaymentStatus::Pending,
            ]);

            return ['checkout_url' => $session->url, 'payment_id' => $payment->id];
        });
    }
}
```

### StripeWebhook\HandleAction(D-3 で冪等性 5 ステップ明示)

```php
namespace App\UseCases\StripeWebhook;

class HandleAction
{
    public function __construct(private PurchaseQuotaAction $purchase) {}

    /**
     * Stripe Webhook イベントを処理する。冪等性を保証する設計。
     *
     * @param array $event Stripe Webhook event(VerifyStripeSignature middleware で検証済)
     */
    public function __invoke(array $event): void
    {
        match ($event['type']) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'checkout.session.expired' => $this->handleCheckoutExpired($event),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event),
            default => null,  // 未対応イベントは無視
        };
    }

    /**
     * checkout.session.completed の処理(D-3 で冪等性 5 ステップ明示)
     *
     * Step 1. DB::transaction 内で stripe_checkout_session_id をキーに Payment を lockForUpdate で SELECT
     * Step 2. Payment が存在しないなら return(ログのみ、不整合だが Stripe 側の操作で発生する可能性)
     * Step 3. payment.status が既に succeeded なら return(冪等性ガード、二重処理防止)
     * Step 4. payment を succeeded に UPDATE(stripe_payment_intent_id / paid_at セット)
     * Step 5. PurchaseQuotaAction を呼んで MeetingQuotaTransaction(purchased) を INSERT
     */
    private function handleCheckoutCompleted(array $event): void
    {
        DB::transaction(function () use ($event) {
            $session = $event['data']['object'];

            // Step 1: lockForUpdate で取得
            $payment = Payment::where('stripe_checkout_session_id', $session['id'])
                ->lockForUpdate()->first();

            // Step 2: Payment 未存在は skip
            if (!$payment) {
                Log::warning('Stripe webhook: Payment not found', ['session_id' => $session['id']]);
                return;
            }

            // Step 3: 冪等性ガード(2 回目以降は skip)
            if ($payment->status === PaymentStatus::Succeeded) {
                Log::info('Stripe webhook: Payment already succeeded, skipping', ['payment_id' => $payment->id]);
                return;
            }

            // Step 4: Payment を succeeded に UPDATE
            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'stripe_payment_intent_id' => $session['payment_intent'],
                'paid_at' => now(),
            ]);

            // Step 5: PurchaseQuotaAction で MeetingQuotaTransaction INSERT
            ($this->purchase)($payment);
        });
    }

    private function handleCheckoutExpired(array $event): void
    {
        DB::transaction(function () use ($event) {
            $session = $event['data']['object'];
            Payment::where('stripe_checkout_session_id', $session['id'])
                ->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Failed]);
        });
    }

    private function handlePaymentFailed(array $event): void
    {
        DB::transaction(function () use ($event) {
            $paymentIntent = $event['data']['object'];
            Payment::where('stripe_payment_intent_id', $paymentIntent['id'])
                ->update(['status' => PaymentStatus::Failed]);
        });
    }
}

class PurchaseQuotaAction
{
    public function __invoke(Payment $payment): MeetingQuotaTransaction
    {
        return MeetingQuotaTransaction::create([
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased,
            'amount' => $payment->quantity,
            'related_payment_id' => $payment->id,
            'occurred_at' => $payment->paid_at,
        ]);
    }
}
```

## MeetingQuotaService(D-3 で history メソッド詳細設計)

```php
class MeetingQuotaService
{
    /**
     * 残数集計
     */
    public function remaining(User $user): int
    {
        return $user->max_meetings + MeetingQuotaTransaction::where('user_id', $user->id)
            ->whereIn('type', [
                MeetingQuotaTransactionType::Consumed,
                MeetingQuotaTransactionType::Refunded,
                MeetingQuotaTransactionType::Purchased,
                MeetingQuotaTransactionType::AdminGrant,
            ])
            ->sum('amount');
    }

    /**
     * 履歴一覧(D-3 で詳細設計)
     *
     * @param User $user 対象
     * @param ?MeetingQuotaTransactionType $type フィルタ
     * @param int $perPage ページサイズ
     * @return LengthAwarePaginator
     */
    public function history(
        User $user,
        ?MeetingQuotaTransactionType $type = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return MeetingQuotaTransaction::query()
            ->where('user_id', $user->id)
            ->with(['relatedMeeting.enrollment.certification', 'relatedPayment.meetingQuotaPlan', 'grantedBy'])  // Eager Loading
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('occurred_at')
            ->paginate($perPage);
    }
}
```

`MeetingQuotaTransaction` Model に必要なリレーション:
- `belongsTo(Meeting, related_meeting_id, relatedMeeting)`
- `belongsTo(Payment, related_payment_id, relatedPayment)`
- `belongsTo(User, granted_by_user_id, grantedBy)`

## Controller(D-4 で API 契約明示)

`app/Http/Controllers/`:

| Controller | Method | Route | Action | Middleware |
|---|---|---|---|---|
| `Admin\MeetingQuotaPlanController` | `index` | `GET /admin/meeting-quota-plans` | `MeetingQuotaPlan\IndexAction` | `auth + role:admin` |
| `Admin\MeetingQuotaPlanController` | `create` / `store` | `POST /admin/meeting-quota-plans` | `MeetingQuotaPlan\StoreAction` | 同上 |
| `Admin\MeetingQuotaPlanController` | `show` / `edit` / `update` / `destroy` | (RESTful) | `MeetingQuotaPlan\*Action` | 同上 |
| `Admin\MeetingQuotaPlanStatusController` | `publish` / `archive` / `unarchive` | `POST /admin/meeting-quota-plans/{plan}/publish` etc. | `MeetingQuotaPlan\PublishAction` etc. | 同上 |
| `CheckoutController` | `select` | `GET /meeting-quota/checkout` | (Blade 描画) | `auth + role:student + EnsureActiveLearning` |
| `CheckoutController` | `create` | `POST /meeting-quota/checkout` | `CreateCheckoutSessionAction` | 同上 |
| `CheckoutController` | `success` | `GET /meeting-quota/success` | `CheckoutSuccessAction` | 同上 |
| `HistoryController` | `index` | `GET /meeting-quota/history` | **`MeetingQuotaService::history($user)`**(D-3) | 同上 |
| `Webhooks\StripeWebhookController` | `handle` | `POST /webhooks/stripe` | `StripeWebhook\HandleAction` | **`VerifyStripeSignature` のみ**(auth なし) |

> **`Admin\UserController::grantMeetingQuota` は [[user-management]] 所有**。`.claude/rules/backend-usecases.md`「Feature 間連携のラッパー Action」規約に従い、user-management 配下のラッパー `User\GrantMeetingQuotaAction` が本 Feature の `AdminGrantQuotaAction($user, $amount, $admin, $reason)` を内部 DI 呼出する。

## FormRequest(D-4 で明示)

`app/Http/Requests/MeetingQuotaPlan/`:

- **`StoreRequest`**:
  ```php
  public function rules(): array
  {
      return [
          'name' => ['required', 'string', 'max:100'],
          'description' => ['nullable', 'string', 'max:2000'],
          'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
          'price' => ['required', 'integer', 'min:0', 'max:1000000'],
          'sort_order' => ['nullable', 'integer', 'min:0'],
      ];
  }
  ```
- `UpdateRequest`: 同 rules
- `IndexRequest`: `status` / `keyword` 任意フィルタ

`app/Http/Requests/MeetingQuota/`:
- `CheckoutRequest`(`meeting_quota_plan_id: required ulid exists:meeting_quota_plans,id,status,published`)

## Policy(D-4 で真偽値マトリクス明示)

`MeetingQuotaPlanPolicy`:

| メソッド | admin | coach | student |
|---|---|---|---|
| `viewAny` | true | false | false |
| `view` | true | false | false |
| `create` / `update` / `delete` / `publish` / `archive` | true | false | false |

`MeetingQuotaPolicy`(受講生向け):
- `purchase(User $user)`: `user->role === Student && user->status === InProgress` のみ true
- `viewHistory(User $auth, User $target)`: `$auth->id === $target->id`(本人のみ)

## Route

```php
// admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('meeting-quota-plans', Admin\MeetingQuotaPlanController::class);
    Route::post('meeting-quota-plans/{plan}/publish', [Admin\MeetingQuotaPlanStatusController::class, 'publish']);
    Route::post('meeting-quota-plans/{plan}/archive', [Admin\MeetingQuotaPlanStatusController::class, 'archive']);
    Route::post('meeting-quota-plans/{plan}/unarchive', [Admin\MeetingQuotaPlanStatusController::class, 'unarchive']);
});

// student
Route::middleware(['auth', 'role:student', EnsureActiveLearning::class])
    ->prefix('meeting-quota')->name('meeting-quota.')->group(function () {
        Route::get('checkout', [CheckoutController::class, 'select'])->name('checkout.select');
        Route::post('checkout', [CheckoutController::class, 'create'])->name('checkout.create');
        Route::get('success', [CheckoutController::class, 'success'])->name('success');
        Route::get('history', [HistoryController::class, 'index'])->name('history');
    });

// Stripe Webhook(認証なし、署名検証のみ)
Route::post('webhooks/stripe', [Webhooks\StripeWebhookController::class, 'handle'])
    ->middleware('stripe.signature')
    ->name('webhooks.stripe');
```

`Kernel.php` の `$middlewareAliases` に `'stripe.signature' => VerifyStripeSignature::class` 追加。**`stripe.signature` ルートを CSRF 検証から除外**(`$except` 配列に `/webhooks/stripe` 追加)。

## VerifyStripeSignature Middleware

```php
class VerifyStripeSignature
{
    public function handle(Request $request, Closure $next)
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig, config('services.stripe.webhook_secret')
            );
            $request->merge(['stripe_event' => $event->toArray()]);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new StripeWebhookSignatureInvalidException();
        }
        return $next($request);
    }
}
```

## エラーハンドリング

`app/Exceptions/MeetingQuota/`:
- `InsufficientMeetingQuotaException`(HTTP 409)
- `MeetingQuotaPlanNotDeletableException`(HTTP 409)
- `MeetingQuotaPlanNotPublishedException`(HTTP 422)
- `MeetingQuotaPlanInvalidTransitionException`(HTTP 409)
- `UserNotInProgressException`(HTTP 403)
- `StripeWebhookSignatureInvalidException`(HTTP 400)

## Blade テンプレ

- `views/admin/meeting-quota-plans/index.blade.php`(SKU 一覧)
- `views/admin/meeting-quota-plans/create.blade.php` / `edit.blade.php`(フォーム: name / description / meeting_count / price / sort_order)
- `views/admin/meeting-quota-plans/show.blade.php`(詳細 + 購入履歴)
- `views/meeting-quota/checkout-select.blade.php`(受講生の SKU 選択画面、dashboard から遷移)
- `views/meeting-quota/success.blade.php`(決済完了画面)
- `views/meeting-quota/history.blade.php`(受講生用、`MeetingQuotaService::history` の結果を表示)

> `views/admin/users/_modals/grant-meeting-quota.blade.php` は [[user-management]] 所有

## Test 戦略

- `tests/Feature/Http/Admin/MeetingQuotaPlanControllerTest.php`(SKU CRUD + 状態遷移)
- `tests/Feature/Http/MeetingQuota/CheckoutControllerTest.php`(Stripe Checkout Session 作成、`Http::fake` でモック)
- `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php`(`Stripe\Webhook::constructEvent` を `Http::fake` でモック)
- **`tests/Feature/UseCases/MeetingQuota/GrantInitialQuotaActionTest.php`(D-1)** — 統一シグネチャ網羅(`($user, $amount)` / `($user, $amount, $admin)` / `($user, $amount, $admin, $reason)` / `granted_by_user_id` が admin 経由時にのみ記録) / `amount <= 0` で `InvalidArgumentException`
- `tests/Feature/UseCases/MeetingQuota/ConsumeQuotaActionTest.php`(残数不足で 409)
- `tests/Feature/UseCases/MeetingQuota/RefundQuotaActionTest.php`
- **`tests/Feature/UseCases/MeetingQuota/StripeWebhook\HandleActionTest.php`(D-3)** — 5 ステップ網羅: (1) `checkout.session.completed` で Payment 未存在 → 何もしない / (2) 既 succeeded → 二重処理なし(冪等性、Transaction が 2 件にならない) / (3) Pending Payment が succeeded に UPDATE / (4) `checkout.session.expired` で pending → failed / (5) `payment_intent.payment_failed` で failed
- `tests/Feature/UseCases/MeetingQuota/AdminGrantQuotaActionTest.php`(`granted_by_user_id = admin.id` 確認)
- **`tests/Unit/Services/MeetingQuotaServiceTest.php`** — `remaining` 計算(初期付与 + 消費 + 購入 + 返却 + 手動付与の組合せ) + **`history` 動作**(D-3、type フィルタ / Eager Loading / paginate / orderByDesc('occurred_at'))
