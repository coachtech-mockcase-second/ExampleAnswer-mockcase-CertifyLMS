# S-A-03 Stripe 連携(追加面談購入)

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-03` |
| Feature 連番 | `meeting-quota-02` |
| Feature | meeting-quota |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Advance |
| 工数 (h) | 16 |
| 依存チケット | `S-B-02`(面談パックマスタ管理) |

## 概要

受講生が残面談回数を Stripe Checkout で追加購入できる動線を新規実装する。受講生はダッシュボードのプラン情報パネルから面談パック(`S-B-02` で admin が管理する SKU)を選択して Stripe Checkout に遷移し、決済完了後に Stripe Webhook 経由で残数加算が反映される。決済情報を扱う `payments` テーブルを新規追加し、Webhook 受信時の冪等性を担保する。

## 背景・目的

- **現状の問題**: 提供 PJ では、受講生の残面談回数は受講プラン契約時に初期付与される値(`User.max_meetings`)のみで、使い切ったあとは次の契約更新まで増やせない。残数 0 で予約不可になった受講生は学習リズムを崩しコーチ面談を諦めるしかなく、ビジネス側も追加収益機会を取り逃している。
- **達成したい状態**: 受講生が残数 0 になっても自力でその場で追加面談パックを購入し、決済完了即座に予約画面で残数加算が反映される。運営は admin で面談パックの SKU を CRUD(`S-B-02` で実装済)するだけで、価格 / 回数の運用を柔軟にコントロールできる。
- **価値・優先度**: 学習継続率の維持 + 追加収益動線の構築 + Stripe 連携 / Webhook 冪等性 / 署名検証 という Pro 生レベルの外部決済連携体験を兼ねる Advance スコープの代表チケット。

## ユーザーストーリー

- **受講生(student)として**、残面談回数が 0 になっても、その場で追加面談を購入してすぐ予約できる状態を期待する。なぜなら、学習リズムを崩したくないから。
- **受講生として**、購入の流れが直感的で、決済画面は信頼できる決済プラットフォームに任されている安心感を期待する。なぜなら、自分のカード情報を学習サービス側に直接渡したくないから。
- **受講生として**、決済完了後すぐに残面談回数が反映され、続けて面談予約に進める導線を期待する。なぜなら、購入してからまた残数が反映されるのを待つ体験はストレスだから。
- **受講生として**、決済が失敗 / 中断した場合は残数が変わらないことを期待する。なぜなら、課金されていないのに残数が増えるのは不安、逆に課金されたのに残数が増えないのも困るから。
- **管理者(admin)として**、Stripe Webhook が二重で到着しても残数が二重加算されないことを期待する。なぜなら、決済プロバイダ側の再送で残数会計が崩れたら監査が成立しないから。
- **管理者として**、決済の改ざんを構造的に防げる仕組みを期待する。なぜなら、Webhook を偽装した不正残数加算を防ぎたいから。
- **コーチ(coach) / 管理者として**、自分の画面に「追加面談を購入」動線が表示されない。なぜなら、購入動線は受講生専用機能で、自分のロールに関係ないから。

## やること

### 追加面談購入動線(受講生)

- **面談パック選択画面**: 受講生(学習中)のみ可、コーチ / 管理者 / 学習中以外の受講生は 403。公開中の面談パック一覧を並び順で表示し、各パックの名前 / 面談回数 / 価格を確認できる
- **Stripe Checkout への遷移**: 受講生が購入する面談パックを選択して購入ボタンを押下すると、Stripe Checkout Session が作成され、Stripe の外部決済画面にリダイレクトされる。決済情報を扱う Payment レコードが pending 状態で保存される
- **決済完了画面**: 受講生が Stripe 上で決済を完了すると LMS に戻り、決済完了画面(残数加算は Webhook 経由で確定する旨 + ダッシュボードに戻る案内)が表示される
- **決済キャンセル時**: 受講生が Stripe 上で決済をキャンセル / ブラウザバックすると、ダッシュボードに戻る(Payment は pending のまま、後で `checkout.session.expired` Webhook で failed に遷移)
- **公開停止 / 下書きパックの購入拒否**: 受講生が公開停止 / 下書き状態の面談パックの ID をクエリで指定しても 422 で拒否(購入動線に並ばないが、URL 改ざんによる購入をブロック)
- **学習中以外のステータスの購入拒否**: 受講生のステータスが学習中以外(招待中 / 修了 / 退会)の場合、購入動線にアクセスすると 403

### Stripe Webhook 受信(認証なし、署名検証必須)

- **Webhook 受信エンドポイント**: Stripe からのイベント通知を受け取る公開エンドポイント。認証ミドルウェアは適用せず、署名検証ミドルウェアで HMAC-SHA256 ベースの改ざん検出を行う
- **署名検証失敗**: ヘッダの署名と Webhook シークレットを使った検証が失敗した場合、400 を返してリクエストを拒否
- **決済完了イベント**: `checkout.session.completed` イベント受信時、対応する Payment を成功状態に遷移 + Payment 情報をもとに購入回数分の残数加算トランザクションを INSERT
- **決済失敗 / 期限切れイベント**: `checkout.session.expired` / `payment_intent.payment_failed` イベント受信時、対応する Payment を失敗状態に遷移(残数加算なし)
- **Webhook 冪等性**: 同一の決済成功イベントが Stripe から複数回再送されても、残数加算は 1 回のみ(2 回目以降はスキップ)
- **未対応イベント**: 上記 3 種類以外のイベントは無視(レスポンス 200 で受信成功を返す、Stripe 側の再送を止める)

### 共通の振る舞い

- **残数集計の即時反映**: Webhook で残数加算トランザクションが INSERT されると、その瞬間から受講生の残数集計に反映される(ダッシュボード / 予約画面など)
- **決済処理は単一トランザクション**: Stripe Checkout Session 作成 + Payment の pending INSERT を 1 トランザクション内で実行 / Webhook の決済完了処理は Payment の成功遷移 + 残数加算トランザクション INSERT を 1 トランザクション内で実行(冪等性ガードも同じトランザクション内で `lockForUpdate`)
- **stripe_payment_intent_id / stripe_checkout_session_id の UNIQUE 制約**: 同じ Stripe 決済識別子で複数の Payment レコードが作られない構造保証
- **タイミング遅延の透明性**: 受講生は決済完了画面で「残数加算は決済情報の確定後に反映される」旨を案内され、Webhook 遅延中にダッシュボードを再ロードすれば反映される

## やらないこと

- 面談回数のサブスクリプション(自動再課金)— 都度購入のみ
- 面談回数の有効期限 — 無期限(受講プラン期間内)
- 面談回数の他者への譲渡
- 円以外の通貨対応 — 円のみ
- Stripe 以外の決済プロバイダ(PayPal / Square / Pay.jp 等)
- 返金フロー(受講生主導)— Stripe ダッシュボードからの admin 操作のみ(refund Webhook 受信で Payment 状態は更新するが、残数返却 transaction の自動 INSERT はしない、admin の手動判断に委ねる)
- 面談パックの在庫管理(デジタル商品で無限在庫)
- 管理者の受講生詳細画面からの面談回数手動付与 UI — 提供 PJ で実装済の `AdminGrantQuotaAction` を使うが、UI / Controller は本チケットスコープ外
- 残数ゼロ通知 / 購入リマインダーのプッシュ通知 — 本チケットでは決済動線のみ
- 面談パックのまとめ買い割引 / クーポンコード — 単品購入のみ
- 領収書 / 請求書 PDF 自動発行 — Stripe ダッシュボードからの手動操作で代替
- 多段階の承認フロー(法人契約等)

## Seeder 設計

> `migrate:fresh --seed` 直後に動作確認できるよう、シナリオに紐付けたレコード単位で具体化する。

**前提**(他 Seeder で投入される想定): 受講生 A〜D / 管理者 / 公開中面談パック(`S-B-02` の `MeetingPackSeeder` で投入済、`published × 3` + `draft × 1` + `archived × 1`) / 受講生 A〜D 各々に `User.max_meetings` 初期付与済 / `meeting_quota_transactions` の初期付与(`granted_initial`)レコードが提供 PJ の既存 Seeder で投入済

`PaymentSeeder`(新規、`DatabaseSeeder` で `MeetingPackSeeder` の後に実行):

| レコード | 内容 | 動作確認用途 |
|---|---|---|
| payment_1 | 受講生 A / type=`extra_meeting_quota` / 公開中 5 回パック / status=`succeeded` / `paid_at` セット済 / 対応する `MeetingQuotaTransaction(type=purchased, amount=+5)` あり | 過去の購入履歴の表示確認 / 受講生 A の残数が初期付与 + 5 で計算される確認 |
| payment_2 | 受講生 B / 公開中 1 回パック / status=`pending` / `paid_at` NULL / 対応する transaction なし | pending 状態(Stripe 決済中)の Payment は残数に反映されない確認 / 後で `checkout.session.expired` 受信時の failed 遷移確認 |
| payment_3 | 受講生 C / 公開中 10 回パック / status=`failed` / `stripe_payment_intent_id` あり / 対応する transaction なし | 決済失敗時に残数加算が走らない確認 |

- **DatabaseSeeder への追加順序**: `UserSeeder` → `CertificationSeeder` → 既存の `MeetingPackSeeder` → 本 `PaymentSeeder` → 既存の関連 transactions seeder の更新(payment_1 に対応する purchased transaction を追加)

> 各 Payment レコードは **テスト・教材用のダミー Stripe 識別子**(`'cs_test_xxx'` / `'pi_test_xxx'`)で投入される。実際に Stripe API には通らないため、Webhook 受信動作の実機確認には Stripe CLI の `stripe listen` + `stripe trigger checkout.session.completed` 等で疑似イベントを送信する手順を `README` に明記する。

## 受け入れ条件

- [ ] **面談パック選択画面 - 認可**: 受講生(学習中)が `/meeting-quota/checkout` にアクセスすると公開中の面談パック一覧画面が表示される。コーチ / 管理者 / 学習中以外の受講生は 403
- [ ] **面談パック選択画面 - 並び順 + 表示内容**: 公開中の面談パックのみが並び順で表示され、各パックの名前 / 面談回数 / 価格が確認できる(下書き / アーカイブ状態は表示されない)
- [ ] **Stripe Checkout 遷移 - 認可**: 受講生(学習中)が購入アクションを実行すると Stripe Checkout 画面に外部リダイレクト(`redirect()->away()`)される。コーチ / 管理者 / 学習中以外の受講生は 403
- [ ] **Stripe Checkout 遷移 - Payment pending 永続化**: 購入アクション成功時に Payment レコードが pending 状態 + Stripe Checkout Session ID + 価格 + 購入回数で INSERT される
- [ ] **Stripe Checkout 遷移 - 公開停止 / 下書きパック拒否**: 公開中以外(下書き / アーカイブ)の面談パック ID で購入アクションを呼ぶと 422 が返る。Payment は INSERT されない
- [ ] **Stripe Checkout 遷移 - 学習中以外拒否**: 学習中以外のステータスの受講生が購入アクションを呼ぶと 403 が返る。Payment は INSERT されない
- [ ] **決済完了画面 - 表示**: 受講生が Stripe で決済完了して LMS の決済完了画面に戻ると、購入完了の案内 + ダッシュボードへの導線が表示される(残数加算は Webhook 経由で反映される旨の案内を含む)
- [ ] **Webhook - 署名検証成功**: Stripe からの正当な署名付きリクエストは Webhook エンドポイントで受信され、200 が返る
- [ ] **Webhook - 署名検証失敗**: ヘッダの署名と環境変数のシークレットを使った検証が失敗した場合、400 が返り、Payment / 残数加算 は変更されない
- [ ] **Webhook - 決済完了イベント**: `checkout.session.completed` イベント受信時、対応する Payment が成功状態に遷移し、`paid_at` がセットされ、`stripe_payment_intent_id` が記録される。同じトランザクション内で購入回数分の残数加算トランザクションが INSERT される
- [ ] **Webhook - 決済失敗イベント**: `payment_intent.payment_failed` イベント受信時、対応する Payment が失敗状態に遷移する。残数加算トランザクションは INSERT されない
- [ ] **Webhook - 決済期限切れイベント**: `checkout.session.expired` イベント受信時、pending 状態の対応する Payment が失敗状態に遷移する。残数加算トランザクションは INSERT されない
- [ ] **Webhook - 冪等性(成功 2 回)**: 同一の `checkout.session.completed` イベントが Stripe から再送されても、Payment の成功遷移と残数加算は 1 回のみ実行される(2 回目以降はスキップ、残数会計が崩れない)
- [ ] **Webhook - 冪等性(成功 → 失敗逆順)**: `checkout.session.completed` 後に `payment_intent.payment_failed` イベントが Stripe の再送順序の都合で到着しても、既に成功状態の Payment は失敗状態に巻き戻らない
- [ ] **Webhook - 対応 Payment 不在**: Stripe Checkout Session ID に対応する Payment レコードが LMS に存在しないイベントを受信した場合、200 を返してログを残しスキップする(警告ログのみで例外にしない)
- [ ] **Webhook - 未対応イベント**: 上記 3 種類以外のイベントは無視され、200 が返る(Stripe 側の再送を止める)
- [ ] **残数集計即時反映**: 残数加算トランザクションが INSERT された直後に受講生の残数集計に反映される(ダッシュボード / 予約画面の表示数値が変わる)
- [ ] **stripe_checkout_session_id UNIQUE**: 同じ Stripe Checkout Session ID で複数の Payment が INSERT されない(DB 制約違反で 500 ではなく、適切なエラーハンドリング)
- [ ] **stripe_payment_intent_id UNIQUE**: 同じ Stripe PaymentIntent ID で複数の Payment が成功状態に遷移しない

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/meeting-quota/checkout` | 受講生のみ可、公開中の面談パック一覧画面を表示 |
| POST | `/meeting-quota/checkout` | 受講生のみ可、Stripe Checkout Session を作成 + Payment を pending で INSERT + 外部リダイレクト(`redirect()->away($session->url)`) |
| GET | `/meeting-quota/success?session_id={CHECKOUT_SESSION_ID}` | 決済完了画面(購入完了の案内 + ダッシュボードへの導線)、`session_id` クエリから対応する Payment を取得して画面に表示 |
| POST | `/webhooks/stripe` | 認証なし、`stripe.signature` Middleware で署名検証、Event 配列を `StripeWebhook\HandleAction` に委譲、200 OK + JSON `{received: true}` |

> Stripe 側の `cancel_url` には `/dashboard` を指定し、決済キャンセル時は受講生をダッシュボードに戻す(Payment は pending のまま後で expired Webhook で failed に遷移)。

### データモデル

**新規テーブル**: `payments`(ULID 主キー、SoftDelete 採用 = 会計監査要件)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | `$table->ulid('id')->primary()` |
| user_id | ulid | ✓ | users.id, ON DELETE RESTRICT | 購入者 |
| type | varchar(30) | ✓ | | 決済種別(`extra_meeting_quota` 固定、将来の他購入種別への拡張余地) |
| meeting_pack_id | ulid | ✓ | meeting_packs.id, ON DELETE RESTRICT | 購入対象の面談パック |
| stripe_payment_intent_id | varchar(255) | | UNIQUE | Stripe PaymentIntent ID、決済完了時にセット |
| stripe_checkout_session_id | varchar(255) | ✓ | UNIQUE | Stripe Checkout Session ID、Payment INSERT 時にセット |
| amount | unsignedInteger | ✓ | | 決済額(円、スナップショット = Stripe 上の金額と一致) |
| quantity | unsignedSmallInteger | ✓ | | 購入面談回数(`meeting_pack.meeting_count` のスナップショット) |
| status | varchar(20) | ✓ | | `PaymentStatus` Enum cast(`pending` / `succeeded` / `failed` / `refunded`)、デフォルト `pending` |
| paid_at | timestamp | | | 決済成功時にセット |
| created_at | timestamp | | | `$table->timestamps()` |
| updated_at | timestamp | | | `$table->timestamps()` |
| deleted_at | timestamp | | | `$table->softDeletes()` |

- **インデックス**: `user_id` / `(status, paid_at)` 複合 / `stripe_payment_intent_id` UNIQUE / `stripe_checkout_session_id` UNIQUE / `deleted_at`
- **Enum**: `PaymentStatus`(Pending / Succeeded / Failed / Refunded、`label()` 日本語) / 既存の `MeetingQuotaTransactionType` に `Purchased` 値を使う(提供 PJ で既存)
- **リレーション**: `Payment::user(): BelongsTo<User>` / `Payment::meetingPack(): BelongsTo<MeetingPack>` / `Payment::meetingQuotaTransactions(): HasMany<MeetingQuotaTransaction>`(`related_payment_id` 経由)
- **SoftDelete 採用**: 会計監査要件(税務監査 / 返金履歴の整合性)、運用復旧時の参照用

**既存テーブル変更**: なし(`meeting_quota_transactions` は提供 PJ で既存、本チケットでは新規カラム追加なし)

### バリデーション

`CheckoutRequest`(`POST /meeting-quota/checkout`):

| 入力項目 | ルール | 推奨エラーメッセージ |
|---|---|---|
| meeting_pack_id | required / ulid / exists:meeting_packs,id WHERE status=published | 面談パックは必須です。<br>選択された面談パックは購入できません。 |

- `authorize()` で Gate `purchase-meeting-quota`(`MeetingQuotaPolicy::purchase`)を呼ぶ。Policy は受講生 + 学習中ステータスのみ true
- 公開中以外の面談パック ID 指定は `Rule::exists()` の `where('status', 'published')` で 422

Webhook 受信エンドポイントには FormRequest を使わず、`VerifyStripeSignature` Middleware が `Stripe-Signature` ヘッダ + 環境変数のシークレットで HMAC-SHA256 検証を行い、検証成功時に Event 配列を `stripe_event` キーで `Request` に merge する。

### 認可設計

**Policy**: `MeetingQuotaPolicy`(本チケットで新設)+ 既存 `MeetingPackPolicy`(`S-B-02` で実装済)

| メソッド | ロール × 判定 |
|---|---|
| `MeetingQuotaPolicy::purchase` | 受講生 かつ status=`in_progress` のみ ✅(コーチ / 管理者 / 学習中以外の受講生 ❌) |

- `purchase-meeting-quota` Gate を `AuthServiceProvider` で `Gate::define('purchase-meeting-quota', [MeetingQuotaPolicy::class, 'purchase'])` で登録
- 受講生選択画面・購入アクション両方で `$this->authorize('purchase-meeting-quota')` または `FormRequest::authorize()` 経由で呼ぶ
- Webhook エンドポイントには認可なし(認証ミドルウェアも適用しない、署名検証ミドルウェアのみ)

### API 仕様

Webhook エンドポイントは Stripe からの公開エンドポイントなので、純粋な HTTP POST として受け取る。

| エンドポイント | リクエスト | レスポンス | 認証 |
|---|---|---|---|
| `POST /webhooks/stripe` | Stripe Event payload(JSON、`type` / `data.object` 等)+ `Stripe-Signature` ヘッダ | 200 + `{received: true}` / 400(署名検証失敗 or 不正 payload) | なし(署名検証のみ) |

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `PaymentStatus` Enum / Payment Model リレーション(user / meetingPack / meetingQuotaTransactions) / `CreateCheckoutSessionAction`(Mockery で `StripeClient` をスタブ → セッション作成成功 / Payment INSERT) / `PurchaseQuotaAction`(残数加算 transaction INSERT) / `StripeWebhook\HandleAction`(checkout.session.completed → Payment 成功 + transaction INSERT / 冪等性 / expired → failed / payment_failed → failed / 未対応 type → 無視 / Payment 不在 → 警告ログ) |
| Feature | `/meeting-quota/checkout`(GET = 受講生 → 一覧 / 他ロール → 403 / 学習中以外 → 403) / POST = Stripe Checkout 遷移 + Payment pending / 公開停止パック → 422 / 学習中以外 → 403 / 認可拒否 → 403 / `/meeting-quota/success`(決済完了画面 + 対応 Payment 表示) / `POST /webhooks/stripe`(署名検証 OK → Action 呼出 + 200 / 署名検証 NG → 400 / 不正 type → 400 / 未対応イベント → 200 + 無処理 / 冪等 2 回 → transaction 1 件のみ) |
| Middleware | `VerifyStripeSignature`(正規シグネチャ → 次へ / 不正シグネチャ → `StripeWebhookSignatureInvalidException` / シークレット未設定 → 同例外) |

### アーキテクチャ判断

- **採用技術**: Eloquent + UseCases (Action) + Service(StripeClient binding) + Policy + FormRequest + Blade + Middleware + Stripe SDK(`stripe/stripe-php`) + `DB::transaction` + `lockForUpdate`
- **設計判断**:
  1. **`stripe/stripe-php` パッケージ採用**: 公式 Stripe SDK を `composer require stripe/stripe-php` で導入。`Stripe\StripeClient` を `AppServiceProvider::register()` で `singleton()` 登録し、`config('services.stripe.secret')` で初期化。Action は `StripeClient` を constructor injection で受け取る
  2. **Webhook 署名検証 Middleware 分離**: 検証ロジックを `App\Http\Middleware\VerifyStripeSignature` に集約。`Stripe\Webhook::constructEvent($payload, $signature, $secret)` で HMAC-SHA256 検証 → 成功時に Event を配列化して `Request` に merge → Controller / Action は検証済 Event 配列を受け取るだけ。検証失敗は `StripeWebhookSignatureInvalidException`(400) を throw
  3. **Webhook 冪等性ガード**: `StripeWebhook\HandleAction` の `handleCheckoutCompleted` で (1) `stripe_checkout_session_id` で Payment を `lockForUpdate()` で SELECT、(2) 既に `succeeded` 状態なら skip(ログのみ)、(3) `pending` を `succeeded` に UPDATE、(4) `PurchaseQuotaAction` で `MeetingQuotaTransaction(type=purchased, amount=+quantity)` INSERT。「成功 → 失敗」逆順到着への耐性として `handlePaymentFailed` で `WHERE status=pending` 条件を付け、既に成功済の Payment を失敗に巻き戻さない
  4. **CreateCheckoutSessionAction の二重防衛**: (a) `MeetingPack.status !== Published` を Action 内で再検証(`MeetingPackNotPublishedException`、422) — FormRequest の `Rule::exists()` を通過しても Action 内で防衛、(b) `User.status !== InProgress` を再検証(`UserNotInProgressException`、403、ブラウザ HTML 経由ならリダイレクト + フラッシュ変換)
  5. **Webhook ルートの CSRF 例外**: `routes/web.php` 配下に `POST /webhooks/stripe` を配置しつつ、`bootstrap/app.php`(Laravel 11+)/ `VerifyCsrfToken` の `$except` 配列に `webhooks/stripe` を追加して CSRF をスキップ(Stripe からの外部 POST のため)。あるいは `routes/api.php` 配下に配置して `api` プレフィックスを変える方針も可
  6. **Payment INSERT のスナップショット**: Payment レコードの `amount` / `quantity` は MeetingPack のスナップショットを保存(参照ではなく値コピー)。MeetingPack の価格を後から admin が変えても、過去の Payment は購入時点の価格 / 回数で監査可能
  7. **物理削除しない(SoftDelete 採用)**: Payment は会計監査要件で SoftDelete を採用。返金で `refunded` 状態に遷移するパスも備えるが、admin 手動運用に委ねる(自動 refund Webhook は本チケットスコープ外)
  8. **`final` を Action / Middleware で適用**: `CreateCheckoutSessionAction` / `PurchaseQuotaAction` / `StripeWebhook\HandleAction` / `VerifyStripeSignature` は `final class`(`backend-services.md` 規約準拠、テストは Stripe SDK を Mockery でスタブ)
  9. **`success_url` / `cancel_url` の構成**: `success_url = route('meeting-quota.success') . '?session_id={CHECKOUT_SESSION_ID}'`(Stripe がプレースホルダを置換)/ `cancel_url = route('dashboard.index')`。受講生は決済完了後 LMS に戻り、決済キャンセル時はダッシュボードへ
  10. **Webhook はメッセージ受信成功で 200 を返す**: 内部処理(Payment 不在 / 未対応 type 等)で例外を発生させると Stripe が再送を続けるため、未対応はログのみで 200 を返す。署名検証失敗のみ 400 で再送を許容(設定ミスの早期発見)

### 関連ファイルメモ

- `app/Models/Payment.php`(新規、`final` 不要、Factory 含む)
- `app/Models/MeetingPack.php` に `payments(): HasMany<Payment>` リレーションを追加(`S-B-02` で作った Model に追記)
- `app/Models/MeetingQuotaTransaction.php` の `belongsTo(Payment::class, 'related_payment_id', 'relatedPayment')` リレーションを追加(提供 PJ で既存だが、Payment Model 新設により参照確立)
- `app/Models/User.php` に `payments(): HasMany<Payment>` リレーションを追加
- `app/Enums/PaymentStatus.php`(新規)
- `app/Http/Controllers/MeetingQuotaCheckoutController.php`(新規、`select` / `create` / `success`)
- `app/Http/Controllers/Webhooks/StripeWebhookController.php`(新規、`handle`)
- `app/Http/Requests/MeetingQuota/CheckoutRequest.php`(新規)
- `app/Http/Middleware/VerifyStripeSignature.php`(新規)
- `bootstrap/app.php` Middleware エイリアス追加: `'stripe.signature' => VerifyStripeSignature::class` + CSRF 除外 `$except[] = 'webhooks/stripe'`
- `app/UseCases/MeetingQuota/CreateCheckoutSessionAction.php`(新規)
- `app/UseCases/MeetingQuota/PurchaseQuotaAction.php`(新規、`MeetingQuotaTransaction(type=purchased)` INSERT、Webhook から呼ばれる)
- `app/UseCases/StripeWebhook/HandleAction.php`(新規、3 イベント分岐 + 冪等性ガード)
- `app/Policies/MeetingQuotaPolicy.php`(新規、`purchase` メソッド)
- `app/Providers/AuthServiceProvider.php` に `Gate::define('purchase-meeting-quota', ...)` を追加
- `app/Providers/AppServiceProvider.php` に `StripeClient` の singleton binding を追加(`$this->app->singleton(StripeClient::class, fn () => new StripeClient((string) config('services.stripe.secret')))`)
- `app/Exceptions/MeetingQuota/{StripeWebhookSignatureInvalid,MeetingPackNotPublished,UserNotInProgress}Exception.php`(新規)
- `config/services.php` に `'stripe' => ['secret', 'publishable_key', 'webhook_secret']` 設定を追加(`.env` の `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` / `STRIPE_WEBHOOK_SECRET` から読込)
- `database/migrations/*_create_payments_table.php`(新規、ULID 主キー + UNIQUE 制約)
- `database/factories/PaymentFactory.php`(新規、状態 state: `pending` / `succeeded` / `failed` / `refunded`)
- `database/seeders/PaymentSeeder.php`(新規、各状態 1 件)
- `resources/views/meeting-quota/{checkout-select,success}.blade.php`(提供 PJ で既存)
- `routes/web.php` に `Route::middleware(['auth', 'role:student', 'active-learning'])->prefix('meeting-quota')` 配下に checkout 3 ルート + `Route::post('webhooks/stripe', ...)` を追加
- `composer.json` に `"stripe/stripe-php": "^14.x"` を追加(Wave 0b で確定済の場合は本チケット範囲外、未追加なら本チケットで追加)
- `.env.example` に `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` / `STRIPE_WEBHOOK_SECRET` のキー取得手順コメントを追加

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 購入できるロールは? | 受講生(学習中)のみ。コーチ / 管理者 / 学習中以外の受講生(招待中 / 修了 / 退会)は 403 |
| 認可拒否時の HTTP ステータスは 403 / 404? | 403。学習中以外の受講生は購入動線にアクセスすると 403(`UserNotInProgressException` 経由、HTML 経由ならリダイレクト + フラッシュエラーに変換) |
| 公開停止 / 下書きの面談パックの ID を URL に指定したら? | 422(`MeetingPackNotPublishedException`、購入動線に並ばないものの URL 改ざんによる購入をブロック) |
| Stripe Checkout の決済画面は LMS 内で表示する? | 表示しない。Stripe が提供する外部決済画面に `redirect()->away()` で遷移する(信頼性と PCI DSS 対応を Stripe に委譲) |
| 決済完了後の戻り先は? | `/meeting-quota/success?session_id={CHECKOUT_SESSION_ID}`。決済完了画面で「残数加算は Webhook 経由で反映される」旨を案内 + ダッシュボードへの導線 |
| 決済キャンセル時の戻り先は? | `/dashboard`。Payment は pending のまま、後で `checkout.session.expired` Webhook で failed に遷移 |
| 残数加算のタイミングは? | Stripe Webhook の `checkout.session.completed` イベント受信時。受講生が決済完了画面を見た時点ではまだ反映されていない可能性があるため、案内文に「決済情報の確定後に反映」を明記 |
| Webhook の認証は? | 認証ミドルウェアは適用しない(Stripe からの公開エンドポイント)。代わりに `Stripe-Signature` ヘッダと `STRIPE_WEBHOOK_SECRET` を使った HMAC-SHA256 署名検証を必須化 |
| 署名検証失敗時の HTTP ステータスは? | 400(`StripeWebhookSignatureInvalidException`)。Stripe 側が再送するが、設定不整合の早期発見を優先 |
| 同じ Webhook が再送されたら残数は二重加算される? | されない。`stripe_checkout_session_id` で Payment を `lockForUpdate` し、既に成功状態なら処理スキップ(冪等性ガード) |
| 決済成功と失敗が逆順で到着したら? | 失敗イベント処理時に `WHERE status=pending` 条件を付けるため、既に成功状態の Payment は失敗に巻き戻らない |
| 対応する Payment が LMS にないイベントが来たら? | 警告ログを残して 200 を返す(Stripe 側の再送を止める、未対応 event 扱い) |
| 未対応の Stripe イベント type が来たら? | 無視して 200 を返す(将来対応のためログも残さない、Stripe 側の再送を止める) |
| 返金フローは? | admin が Stripe ダッシュボードから手動操作。`charge.refunded` Webhook 受信で Payment 状態を `refunded` に更新する経路は本チケットでは扱わない(将来拡張、admin の手動判断に委ねる) |
| Payment テーブルの SoftDelete 採用理由は? | 会計監査要件(税務 / 返金履歴の整合性)+ 運用復旧時の参照用 |
| `amount` / `quantity` は MeetingPack を参照しないでスナップショット? | スナップショット。MeetingPack の価格 / 回数を後から admin が変えても、過去の Payment は購入時点の値で監査可能 |
| Stripe SDK のバージョン指定は? | `^14.x` 推奨(2026 年現在の最新メジャー、`StripeClient` / `Webhook::constructEvent` API が安定)。`Wave 0b` で確定済なら本チケット範囲外 |
| `.env` に必要な Stripe キーは? | `STRIPE_SECRET_KEY`(API キー) / `STRIPE_PUBLISHABLE_KEY`(フロント Stripe.js 用、本チケットでは Checkout ベースのため未使用) / `STRIPE_WEBHOOK_SECRET`(Webhook 署名検証用)。取得手順は Stripe ダッシュボード > Developers > API keys / Webhooks で発行 |
| 通貨は? | 円(`jpy`)のみ。Stripe Checkout の `line_items.price_data.currency = 'jpy'` で固定 |
| Stripe Checkout の決済方法は? | Stripe デフォルト(クレジットカード / Apple Pay / Google Pay 等、Stripe ダッシュボードの設定に従う) |
| 受講生が複数タブで同時に購入を試みたら? | Stripe Checkout Session は各回独立して作成されるため、2 つの Payment が pending 状態で INSERT される。受講生が片方の Checkout だけ完了した場合、もう片方は `checkout.session.expired` 受信で failed に遷移 |
| 領収書 / 請求書は? | Stripe ダッシュボードからの手動操作で対応(自動 PDF 発行は本チケットスコープ外) |
| ローカル開発で Webhook を試すには? | `stripe listen --forward-to localhost:8000/webhooks/stripe` + `stripe trigger checkout.session.completed` 等で疑似イベント送信(`README` に手順を記載) |
| フラッシュ文言の推奨は? | 公開停止パック購入試行「選択された面談パックは購入できません。」/ 学習中以外購入試行「受講中のユーザーのみ追加面談を購入できます。」(`UserNotInProgressException` の render() で redirect + flash error 変換)(適切な日本語であれば文言の細部は採点対象外) |
| 並び順は? | 公開中の面談パックを `sort_order ASC` → `created_at DESC` で並べる(`S-B-02` の admin マスタ画面と同じ並び順、受講生にも同じ表示順を保つ) |
| 1 トランザクションで複数パックを同時購入できる? | できない(単品購入のみ、まとめ買い割引も本チケットスコープ外) |
