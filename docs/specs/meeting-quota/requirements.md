# meeting-quota 要件定義

> **v3 新規 Feature**（2026-05-16）: 面談回数の付与・消費・追加購入を扱う。`User.max_meetings`（初期付与）+ `MeetingQuotaPlan` マスタ（admin CRUD する追加面談購入の価格セット）+ `MeetingQuotaTransaction` 履歴（INSERT only、iField LMS 流の監査ログ）+ Stripe 連携（Checkout Session + Webhook）+ admin 手動付与 UI。[[mentoring]] との連携（`reserved` で `consumed`、キャンセルで `refunded`）。

## 概要

Certify LMS の **面談回数の付与・消費・追加購入** を一手に担う Feature。受講生は Plan 起点で初期付与された面談回数（`User.max_meetings`）を [[mentoring]] で予約時に消費し、残数 0 で予約不可になる。残数を増やすには [[dashboard]] のプラン情報パネルから **追加面談購入動線**（Stripe Checkout）で `MeetingQuotaPlan`（admin が CRUD する SKU マスタ、例: 1 回 ¥3,000 / 5 回パック ¥12,000）を選択して購入する。admin は受講生詳細画面から手動付与（`admin_grant`）も可能。

残数集計は `MeetingQuotaService::remaining(User): int` で `User.max_meetings + SUM(MeetingQuotaTransaction.amount)` を返す。

## ロールごとのストーリー

- **管理者（admin）**: 追加面談購入用の `MeetingQuotaPlan` マスタを CRUD（例: 「1 回パック ¥3,000」「5 回パック ¥12,000」「10 回パック ¥22,000」）。受講生詳細画面から面談回数を手動付与（`admin_grant`）できる（例: トラブル補填、キャンペーン付与）。`MeetingQuotaTransaction` の履歴を閲覧して監査する。
- **受講生（student）**: [[dashboard]] のプラン情報パネルで残面談回数を確認、残数 0 時は「追加面談を購入」ボタンから `MeetingQuotaPlan` 一覧モーダルを開き、Stripe Checkout に遷移して決済する。決済完了で残数加算 + 履歴記録される。
- **コーチ（coach）**: 本 Feature の直接操作はない。間接的に受講生の予約が消費するだけ。

## 受け入れ基準（EARS形式）

### 機能要件 — A. MeetingQuotaPlan マスタ

- **REQ-meeting-quota-001**: The system shall ULID 主キー / `SoftDeletes` を備えた `meeting_quota_plans` テーブルを提供し、`name`（VARCHAR(100), NOT NULL、例: "5 回パック"）/ `description`（TEXT, nullable）/ `meeting_count`（unsigned smallint, NOT NULL, 1..100）/ `price`（unsigned int, NOT NULL、円単位）/ `stripe_price_id`（VARCHAR(255), nullable、事前作成済の Stripe Price ID を関連付ける場合）/ `status` enum（`draft` / `published` / `archived`）/ `sort_order`（unsigned int, default 0）/ `created_by_user_id` / `updated_by_user_id` / `created_at` / `updated_at` / `deleted_at` を保持する。
- **REQ-meeting-quota-002**: The system shall `App\Enums\MeetingQuotaPlanStatus` enum（`Draft` / `Published` / `Archived`）を提供する。
- **REQ-meeting-quota-010**: When admin が `GET /admin/meeting-quota-plans` にアクセスした際, the system shall 一覧を `sort_order ASC, created_at DESC` でソートして表示する。
- **REQ-meeting-quota-011**: When admin が `POST /admin/meeting-quota-plans` で新規作成した際, the system shall `StoreMeetingQuotaPlanRequest` で `name` / `description` / `meeting_count` / `price` / `stripe_price_id` / `sort_order` を検証し、`status=Draft` で INSERT する。
- **REQ-meeting-quota-012**: When admin が `PUT /admin/meeting-quota-plans/{plan}` で編集した際, the system shall 各カラムを UPDATE する。
- **REQ-meeting-quota-013**: If admin が `published` 状態の MeetingQuotaPlan を DELETE しようとした場合, then the system shall `MeetingQuotaPlanNotDeletableException`（HTTP 409）を返す。`draft` / `archived` のみ削除可。
- **REQ-meeting-quota-020**: When admin が `POST /admin/meeting-quota-plans/{plan}/publish` を呼んだ際, the system shall `status = Draft` を `Published` に遷移させる。
- **REQ-meeting-quota-021**: When admin が `POST /admin/meeting-quota-plans/{plan}/archive` を呼んだ際, the system shall `status = Published` を `Archived` に遷移させる（受講生の購入画面から非表示になるが、過去の `Payment` 履歴は残す）。
- **REQ-meeting-quota-022**: When admin が `POST /admin/meeting-quota-plans/{plan}/unarchive` を呼んだ際, the system shall `status = Archived` を `Draft` に遷移させる(再販売前提なら再度 publish 必須、誤 archive 時の取り戻し用)。違反は `MeetingQuotaPlanInvalidTransitionException`(HTTP 409)。

### 機能要件 — B. MeetingQuotaTransaction（履歴、INSERT only）

- **REQ-meeting-quota-030**: The system shall ULID 主キー（SoftDelete 非採用、不可逆履歴）を備えた `meeting_quota_transactions` テーブルを提供し、`user_id`（FK, NOT NULL）/ `type` enum（`granted_initial` / `purchased` / `consumed` / `refunded` / `admin_grant`）/ `amount` int signed（NOT NULL、消費は負値、付与は正値）/ `related_meeting_id` ULID FK nullable / `related_payment_id` ULID FK nullable / `granted_by_user_id` ULID FK nullable（`admin_grant` 時のみ admin の ID を記録）/ `note` VARCHAR(500) nullable / `occurred_at` datetime NOT NULL / `created_at` / `updated_at` を保持する。
- **REQ-meeting-quota-031**: The system shall `App\Enums\MeetingQuotaTransactionType` enum を提供し、`label()` で日本語ラベル（`初期付与` / `購入` / `消費` / `返却` / `管理者付与`）を返す。
- **REQ-meeting-quota-032**: The system shall `MeetingQuotaTransaction` モデルに `belongsTo(User)` / `belongsTo(Meeting::class, 'related_meeting_id')` / `belongsTo(Payment::class, 'related_payment_id')` / `belongsTo(User::class, 'granted_by_user_id', 'grantedBy')` の 4 リレーションを公開する。
- **REQ-meeting-quota-033**: The system shall `meeting_quota_transactions.(user_id, occurred_at)` 複合 INDEX を提供する（残数集計と履歴閲覧の高速化）。

### 機能要件 — C. 残数集計 Service

- **REQ-meeting-quota-040**: The system shall `App\Services\MeetingQuotaService` を提供し、以下のメソッドを公開する: `remaining(User $user): int` / `history(User $user, int $perPage = 20): LengthAwarePaginator<MeetingQuotaTransaction>`。
- **REQ-meeting-quota-041**: The system shall `remaining(User)` を以下の **一行 formula** で実装する: **`User.max_meetings + SUM(MeetingQuotaTransaction.amount WHERE type IN ('consumed', 'refunded', 'purchased', 'admin_grant'))`**。`granted_initial` は `max_meetings` と二重カウント防止のため SUM 集計から除外する(`User.max_meetings` カラム自体が初期付与 + コース延長付与の累計値を保持、`MeetingQuotaTransaction.granted_initial` は監査ログとしての履歴記録のみ)。
- **REQ-meeting-quota-042**: The system shall `MeetingQuotaService` をステートレス Service として実装し、`DB::transaction()` を内部に持たない。

### 機能要件 — D. 面談予約消費（[[mentoring]] との連携）

- **REQ-meeting-quota-050**: The system shall `App\UseCases\MeetingQuota\ConsumeQuotaAction::__invoke(User $user, Meeting $meeting): MeetingQuotaTransaction` を提供する。
- **REQ-meeting-quota-051**: When [[mentoring]] の `Meeting\StoreAction` が呼ばれる際, the system shall 同一トランザクション内で `ConsumeQuotaAction` を呼び、`MeetingQuotaTransaction` を `type = consumed` / `amount = -1` / `related_meeting_id = $meeting->id` / `occurred_at = now()` で INSERT する。
- **REQ-meeting-quota-052**: When `ConsumeQuotaAction` が呼ばれる際, the system shall (1) `MeetingQuotaService::remaining($user) >= 1` を検証、不足なら `InsufficientMeetingQuotaException`（HTTP 409）を throw、(2) INSERT する。
- **REQ-meeting-quota-053**: The system shall `App\UseCases\MeetingQuota\RefundQuotaAction::__invoke(Meeting $meeting): MeetingQuotaTransaction` を提供する。
- **REQ-meeting-quota-054**: When [[mentoring]] の `Meeting\CancelAction` が呼ばれる際, the system shall 同一トランザクション内で `RefundQuotaAction` を呼び、`MeetingQuotaTransaction` を `type = refunded` / `amount = +1` / `related_meeting_id = $meeting->id` で INSERT する（元の consumed トランザクションを参照して相殺）。

### 機能要件 — E. Stripe 連携（追加面談購入）

- **REQ-meeting-quota-060**: The system shall ULID 主キー / `SoftDeletes` を備えた `payments` テーブルを提供し、`user_id`（FK, NOT NULL）/ `type` enum（`extra_meeting_quota`）/ `meeting_quota_plan_id`（FK, NOT NULL）/ `stripe_payment_intent_id` VARCHAR(255) UNIQUE / `stripe_checkout_session_id` VARCHAR(255) UNIQUE / `amount` unsigned int（円）/ `quantity` unsigned smallint（購入面談回数）/ `status` enum（`pending` / `succeeded` / `failed` / `refunded`）/ `paid_at` datetime nullable / `created_at` / `updated_at` / `deleted_at` を保持する。
- **REQ-meeting-quota-061**: The system shall `App\Enums\PaymentStatus` enum（`Pending` / `Succeeded` / `Failed` / `Refunded`）を提供する。
- **REQ-meeting-quota-070**: When 受講生が `POST /meeting-quota/checkout` を呼んだ際（ペイロード: `meeting_quota_plan_id`）, the system shall `CreateCheckoutSessionAction::__invoke(User $user, MeetingQuotaPlan $plan): array{checkout_url, payment_id}` を実行する。
- **REQ-meeting-quota-071**: When `CreateCheckoutSessionAction` が走る際, the system shall (1) `$plan->status === Published` を検証、違反は `MeetingQuotaPlanNotPublishedException`（HTTP 422）、(2) `$user->status === UserStatus::InProgress` を検証、違反は `UserNotInProgressException`（HTTP 403）、(3) Stripe Checkout Session を Stripe SDK で作成（line_items / success_url / cancel_url / metadata: { user_id, meeting_quota_plan_id }）、(4) `Payment` を `status = pending` / `stripe_checkout_session_id = session.id` / `amount` / `quantity` で INSERT、(5) `{ checkout_url, payment_id }` を返却。
- **REQ-meeting-quota-072**: The system shall Stripe Webhook エンドポイント `POST /webhooks/stripe` を提供し、`auth` Middleware を **適用しない**（Stripe からの公開エンドポイント）。代わりに **`VerifyStripeSignature` Middleware** で署名検証を必須化する（`STRIPE_WEBHOOK_SECRET` を使った HMAC 検証）。
- **REQ-meeting-quota-073**: When `checkout.session.completed` イベントが Webhook で受信された際, the system shall `StripeWebhook\HandleAction::__invoke(array $event): void` を実行する。Action は (1) `stripe_checkout_session_id` で `Payment` を取得、(2) `Payment.status = succeeded` / `paid_at = now()` / `stripe_payment_intent_id = $event.data.object.payment_intent` で UPDATE、(3) `MeetingQuotaTransaction` を `type = purchased` / `amount = +$plan->meeting_count` / `related_payment_id = $payment->id` / `occurred_at = now()` で INSERT、(4) [[notification]] への通知発火は **不採用**（dashboard で残数を確認すれば十分）を実行する。
- **REQ-meeting-quota-074**: When `checkout.session.expired` または `payment_intent.payment_failed` Webhook イベントを受信した際, the system shall `Payment.status = failed` で UPDATE し、`MeetingQuotaTransaction` は INSERT しない。
- **REQ-meeting-quota-075**: If 同一 `stripe_payment_intent_id` で Webhook が重複到着した場合（Stripe の at-least-once delivery）, then the system shall **冪等性を保証**（`Payment.status = succeeded` の場合は idempotent return、`MeetingQuotaTransaction` は重複 INSERT しない）。
- **REQ-meeting-quota-076**: The system shall `success_url` を `/meeting-quota/success?session_id={CHECKOUT_SESSION_ID}` でリダイレクト先として設定し、受講生に決済完了画面を表示する（残数加算は Webhook 経由で確定するため、画面表示は dashboard に戻る案内のみ）。
- **REQ-meeting-quota-077**: The system shall `cancel_url` を `/dashboard` でリダイレクト先として設定する。

### 機能要件 — F. admin 手動付与

- **REQ-meeting-quota-080**: The system shall `App\UseCases\MeetingQuota\AdminGrantQuotaAction::__invoke(User $target, int $amount, User $admin, string $reason): MeetingQuotaTransaction` を提供する。
- **REQ-meeting-quota-081**: When admin が [[user-management]] のユーザー詳細画面から「面談回数手動付与」ボタンを押下した際, the system shall モーダルで `amount`（必須、1..100）と `reason`（必須、max 500）を入力させ、`POST /admin/users/{user}/grant-meeting-quota` で `AdminGrantQuotaAction` を起動する。
- **REQ-meeting-quota-082**: When `AdminGrantQuotaAction` が走る際, the system shall `MeetingQuotaTransaction` を `type = admin_grant` / `amount = +$amount` / `granted_by_user_id = $admin->id` / `note = $reason` / `occurred_at = now()` で INSERT する。

### 機能要件 — G. 受講生 UI（dashboard 経由）

- **REQ-meeting-quota-090**: When 受講生が [[dashboard]] のプラン情報パネル内「追加面談を購入」ボタンを押下した際, the system shall `MeetingQuotaPlan::published()->orderBy('sort_order')->get()` を取得してモーダル一覧表示する（各 SKU: name / `meeting_count` 回 / `price` 円）。
- **REQ-meeting-quota-091**: When 受講生がモーダルで SKU を選択して「購入」ボタン押下した際, the system shall `POST /meeting-quota/checkout` を呼び、`checkout_url` を取得して Stripe Checkout 画面にリダイレクトする。
- **REQ-meeting-quota-092**: The system shall 受講生の `MeetingQuotaTransaction` 履歴を確認できる画面 `GET /meeting-quota/history` を提供する（必須ではない、admin が閲覧する `/admin/users/{user}` の履歴セクションが主）。

### 機能要件 — H. 認可（Policy + Middleware）

- **REQ-meeting-quota-100**: The system shall `/admin/meeting-quota-plans/...` を `auth + role:admin` で保護する。
- **REQ-meeting-quota-101**: The system shall `MeetingQuotaPlanPolicy` を提供し、admin true / coach / student false。
- **REQ-meeting-quota-102**: The system shall `/meeting-quota/checkout` / `/meeting-quota/history` を `auth + role:student + EnsureActiveLearning` で保護する（graduated ユーザーは購入不可）。
- **REQ-meeting-quota-103**: The system shall `/webhooks/stripe` には `auth` Middleware を適用せず、`VerifyStripeSignature` Middleware で署名検証する。
- **REQ-meeting-quota-104**: The system shall `/admin/users/{user}/grant-meeting-quota` を `auth + role:admin` で保護する。

### 非機能要件

- **NFR-meeting-quota-001**: The system shall すべての状態変更 Action（`StoreMeetingQuotaPlanAction` / `UpdateAction` / `DestroyAction` / `PublishAction` / `ArchiveAction` / `ConsumeQuotaAction` / `RefundQuotaAction` / `CreateCheckoutSessionAction` / `StripeWebhook\HandleAction` / `AdminGrantQuotaAction`）を `DB::transaction()` で囲む。
- **NFR-meeting-quota-002**: The system shall `MeetingQuotaService::remaining` を 1 クエリで完結させる（`User.max_meetings + COALESCE(SUM(amount), 0)`）。N+1 を避けるため [[dashboard]] / [[mentoring]] / [[user-management]] からの呼出側で memoize する。
- **NFR-meeting-quota-003**: The system shall 以下 INDEX を提供: `meeting_quota_plans.(status, sort_order)` / `meeting_quota_plans.deleted_at` / `meeting_quota_transactions.(user_id, occurred_at)` 複合 / `meeting_quota_transactions.related_meeting_id` / `meeting_quota_transactions.related_payment_id` / `payments.user_id` / `payments.(status, paid_at)` / `payments.stripe_payment_intent_id` UNIQUE / `payments.stripe_checkout_session_id` UNIQUE / `payments.deleted_at`。
- **NFR-meeting-quota-004**: The system shall ドメイン例外を `app/Exceptions/MeetingQuota/` 配下に実装する（`InsufficientMeetingQuotaException` / `MeetingQuotaPlanNotDeletableException` / `MeetingQuotaPlanNotPublishedException` / `MeetingQuotaPlanInvalidTransitionException` / `UserNotInProgressException` / `StripeWebhookSignatureInvalidException`）。
- **NFR-meeting-quota-005**: The system shall Stripe SDK を `stripe/stripe-php` で導入し、API キー / Webhook シークレットを `.env`（`STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET` / `STRIPE_PUBLISHABLE_KEY`）で管理する。
- **NFR-meeting-quota-006**: The system shall Webhook 受信時に署名検証（HMAC-SHA256）を必須とし、検証失敗は HTTP 400 を返す。
- **NFR-meeting-quota-007**: The system shall Webhook の冪等性を `stripe_payment_intent_id` の UNIQUE 制約と Payment.status の遷移ガードで担保する。

## スコープ外

- **面談回数のサブスクリプション（自動再課金）** — 都度購入のみ
- **面談回数の有効期限** — 無期限（プラン期間内なら利用可、graduated でロック）
- **面談回数の他者への譲渡** — 不可
- **複数の Stripe 通貨対応** — 円のみ
- **Stripe 以外の決済プロバイダ** — Stripe のみ
- **返金フロー（受講生主導）** — Stripe ダッシュボードからの admin 操作のみ（refund Webhook 受信で `Payment.status = refunded`、ただし `MeetingQuotaTransaction.type = refunded` の自動 INSERT は実施しない、admin の手動判断に委ねる）
- **face-to-face 面談購入動線** — Stripe のみ
- **MeetingQuotaPlan の在庫管理** — 不要（デジタル商品、無限在庫）

## 関連 Feature

- **依存元**（本 Feature を利用する）:
  - [[mentoring]] — `Meeting\StoreAction` で `ConsumeQuotaAction`、`Meeting\CancelAction` で `RefundQuotaAction`
  - [[plan-management]] — `ExtendCourseAction` で `User.max_meetings` を加算 + `MeetingQuotaTransaction.granted_initial` を INSERT（本 Feature が提供する `GrantInitialQuotaAction` を呼ぶ）
  - [[dashboard]] — プラン情報パネルで `MeetingQuotaService::remaining` を表示、「追加面談購入」CTA から本 Feature の checkout 動線へ遷移
  - [[user-management]] — admin のユーザー詳細画面で `MeetingQuotaTransaction` 履歴表示 + 手動付与 UI
- **依存先**（本 Feature が前提とする）:
  - [[auth]] — `User` モデル / `UserStatus` Enum / `EnsureActiveLearning` Middleware（graduated ロック）
  - [[mentoring]] — `Meeting` モデル（`related_meeting_id` 参照）
