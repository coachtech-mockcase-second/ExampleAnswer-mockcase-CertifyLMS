# default-enrollment タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-default-enrollment-NNN` / `NFR-default-enrollment-NNN` を参照。
> コマンドはすべて `sail` プレフィックス。
> **本 Feature は cross-cutting infrastructure**: 独自モデルなし、`users.default_enrollment_id` カラム拡張 + Service + Middleware + Action + Component + Endpoint で完結する。

## Step 1: Migration & Model 拡張

- [ ] migration: `add_default_enrollment_id_to_users_table`(`users` テーブルに `default_enrollment_id` ULID nullable + `foreignUlid('default_enrollment_id')->nullable()->after('meeting_url')->constrained('enrollments')->nullOnDelete()`)(REQ-default-enrollment-001, NFR-default-enrollment-004)
- [ ] [[auth]] への追加: `App\Models\User` に `default_enrollment_id` を `$fillable` 追加 + `defaultEnrollment(): BelongsTo` リレーション追加(`belongsTo(Enrollment::class, 'default_enrollment_id', 'id')`)(REQ-default-enrollment-002, REQ-default-enrollment-003)
- [ ] [[enrollment]] への追加: `App\Models\Enrollment` に `defaultedByUser(): HasOne` 逆リレーション追加(`hasOne(User::class, 'default_enrollment_id', 'id')`、admin 画面等での参照用)(NFR-default-enrollment-006)

## Step 2: Service & Exception

- [ ] Service: `App\Services\DefaultEnrollmentService`(`resolveAfterCreate(User, Enrollment): void` / `resolveAfterStatusChange(User, Enrollment): void` / `clearIfInvalid(User): void` の 3 メソッド)(REQ-default-enrollment-010, REQ-default-enrollment-011, REQ-default-enrollment-012, REQ-default-enrollment-013, REQ-default-enrollment-014, REQ-default-enrollment-015, REQ-default-enrollment-016)
- [ ] ドメイン例外: `App\Exceptions\UserPreference\DefaultEnrollmentInvalidTargetException`(HTTP 422、メッセージ「不合格状態の資格はデフォルトに設定できません」)(REQ-default-enrollment-074, NFR-default-enrollment-005)

## Step 3: HTTP 層

- [ ] Controller: `App\Http\Controllers\Settings\SettingsDefaultEnrollmentController`(`update` メソッド 1 つ、Route Model Binding で `Enrollment` を受ける、Action 呼出後 `redirect_to` か `learning.enrollments.show` に 302 redirect)(REQ-default-enrollment-070, REQ-default-enrollment-076)
- [ ] FormRequest: `App\Http\Requests\UserPreference\UpdateDefaultEnrollmentRequest`(`authorize()` 内で `$this->user()->can('view', $enrollment)` 検証、`rules()` で `redirect_to: nullable|string|max:500`)(REQ-default-enrollment-071, REQ-default-enrollment-091, REQ-default-enrollment-092)
- [ ] `routes/web.php` に `PUT /settings/default-enrollment/{enrollment}` ルート追加(`auth + role:student + EnsureActiveLearning` middleware group、name `settings.default-enrollment.update`)(REQ-default-enrollment-070, REQ-default-enrollment-090, REQ-default-enrollment-093)

## Step 4: Action

- [ ] Action: `App\UseCases\UserPreference\UpdateDefaultEnrollmentAction`(`__invoke(User, Enrollment): User`、`$enrollment->status === Failed` で `DefaultEnrollmentInvalidTargetException` throw、`DB::transaction` 内で `users.default_enrollment_id` UPDATE)(REQ-default-enrollment-072, REQ-default-enrollment-074)

## Step 5: Middleware

- [ ] Middleware: `App\Http\Middleware\ResolveDefaultEnrollment`(`handle(Request, Closure $next, string $routeName)`、URL に `{enrollment}` パラメータあれば skip、default 有効性検証 → `clearIfInvalid` → default 有効なら redirect、NULL なら Enrollment 1 件で自動 redirect / 2+ 件 or 0 件で next pass)(REQ-default-enrollment-030, REQ-default-enrollment-032, REQ-default-enrollment-033, REQ-default-enrollment-034, REQ-default-enrollment-035, REQ-default-enrollment-036, REQ-default-enrollment-037, NFR-default-enrollment-002)
- [ ] `app/Http/Kernel.php` の `$middlewareAliases` に `'resolve-default-enrollment' => ResolveDefaultEnrollment::class` を追加(REQ-default-enrollment-030)

## Step 6: Blade Component + JS

- [ ] Blade Component: `resources/views/components/enrollment-switcher.blade.php`(`@props(['variant' => 'inline', 'current' => null])`、3 variant(`sidebar` / `inline` / `empty-state`)で出し分け、dropdown 内に Enrollment 一覧 + ✓ チェック + 「デフォルト」バッジ form。行の資格名リンクは単発切替 GET、バッジ form は PUT /settings/default-enrollment/{enrollment} に POST)(REQ-default-enrollment-050, REQ-default-enrollment-051, REQ-default-enrollment-052, REQ-default-enrollment-053, REQ-default-enrollment-054, REQ-default-enrollment-055, REQ-default-enrollment-056, REQ-default-enrollment-057, REQ-default-enrollment-058)
- [ ] Blade sub-component: `resources/views/components/enrollment-switcher/card.blade.php`(`empty-state` variant 内のカード 1 枚、資格名 + デフォルトバッジ + 単発切替リンク、バッジクリックで PUT /settings/default-enrollment/{enrollment} 発火、資格名リンクで該当 2 階層目に GET 遷移)(REQ-default-enrollment-058, REQ-default-enrollment-084, REQ-default-enrollment-085)
- [ ] JS: `resources/js/components/enrollment-switcher.js`(dropdown 開閉 / 外側クリック・ESC で閉じる / 矢印キー操作、素の JavaScript で実装、Alpine.js / Livewire 不使用)(REQ-default-enrollment-059, NFR-default-enrollment-003)
- [ ] `resources/js/app.js` から `enrollment-switcher.js` を import + `DOMContentLoaded` で初期化(REQ-default-enrollment-059)
- [ ] Tailwind class 整備: `badge-active`(青塗り)/ `badge-inactive`(グレー枠) / `dropdown` / `trigger` 等の最小 styling(NFR-default-enrollment-003)
- [ ] [[auth]] / `frontend-ui-foundation.md` への追加: `resources/views/layouts/_partials/sidebar-student.blade.php` の下部に `<x-enrollment-switcher variant="sidebar" />` を埋込(REQ-default-enrollment-051)

## Step 7: 依存元 Feature への統合連絡(Phase C で各 Feature の spec 改訂時に反映)

> 本 Feature 単体では完結しない統合タスク。各 Feature の `tasks.md` 改訂時に下記を組み込む。

- [ ] [[enrollment]] `StoreAction` に `DefaultEnrollmentService::resolveAfterCreate($user, $newEnrollment)` 呼出を追加(constructor injection、REQ-default-enrollment-018)
- [ ] [[enrollment]] `EnrollmentStatusChangeService` の `recordStatusChange` 後 or 別 Action(`FailAction` / `ReceiveCertificateAction` 等) に `DefaultEnrollmentService::resolveAfterStatusChange` 呼出を追加(REQ-default-enrollment-019)
- [ ] [[enrollment]] `FailExpiredCommand` 内で `resolveAfterStatusChange` 呼出を追加(REQ-default-enrollment-019)
- [ ] [[enrollment]] `/enrollments` 画面の各 Enrollment カードに「★デフォルト」バッジ + 「これをデフォルトにする」フォーム POST 追加(`<x-enrollment-switcher.card>` で再利用)(REQ-default-enrollment-051)
- [ ] [[learning]] `routes/web.php` の `GET /learning` index ルートに `'resolve-default-enrollment:learning.enrollments.show'` Middleware 適用、既存 `BrowseController::index` が default NULL + Enrollment 2+ 件 / 0 件 のケースで `<x-enrollment-switcher variant="empty-state" />` を含む Blade を返すよう修正(REQ-default-enrollment-031, REQ-default-enrollment-080, REQ-default-enrollment-083)
- [ ] [[learning]] 2 階層目以降の Blade 上部に `<x-enrollment-switcher variant="inline" :current="$enrollment" />` を埋込(REQ-default-enrollment-051)
- [ ] [[mock-exam]] URL を `/learning/enrollments/{enrollment}/mock-exams` に再設計、`'resolve-default-enrollment'` Middleware 適用 + empty-state UI + inline Switcher 埋込(REQ-default-enrollment-031, REQ-default-enrollment-081)
- [ ] [[mentoring]] 予約画面(`/meetings/availability`)に `'resolve-default-enrollment:meetings.availability'` Middleware 適用 + empty-state UI + inline Switcher 埋込。履歴一覧(`/meetings`)には適用しない(REQ-default-enrollment-031, REQ-default-enrollment-082)

## Step 8: テスト

### Feature(HTTP)

- [ ] `tests/Feature/Http/Settings/UpdateDefaultEnrollmentTest.php` — 受講生本人の `learning` / `passed` Enrollment で 302 + DB UPDATE / 他者 Enrollment で 403 / `failed` Enrollment で 422 / SoftDelete 済で 404 / coach / admin で 403 / 未ログインで 401(REQ-default-enrollment-070, REQ-default-enrollment-073, REQ-default-enrollment-074, REQ-default-enrollment-090, REQ-default-enrollment-091, REQ-default-enrollment-092, REQ-default-enrollment-093)
- [ ] `tests/Feature/Middleware/ResolveDefaultEnrollmentTest.php` — default 有効時 302 redirect / default NULL + Enrollment 1 件 302 / 2+ 件 200(next pass) / 0 件 200 / default 失効時 NULL リセット → 後段判定(REQ-default-enrollment-032, REQ-default-enrollment-033, REQ-default-enrollment-034, REQ-default-enrollment-035, REQ-default-enrollment-037)

### Feature(UseCases)

- [ ] `tests/Feature/UseCases/UserPreference/UpdateDefaultEnrollmentActionTest.php` — 正常系(UPDATE 成功)/ `status = failed` で例外 / 既存 default 上書き(REQ-default-enrollment-072, REQ-default-enrollment-074)

### Unit(Services)

- [ ] `tests/Unit/Services/DefaultEnrollmentServiceTest.php` — `resolveAfterCreate`(default NULL → セット / 既存 default → 何もしない)/ `resolveAfterStatusChange`(当該 default + 他 1 件 → 振替 / 当該 default + 他 2+ 件 → NULL / 当該 default + 他 0 件 → NULL / 非 default → 何もしない)/ `clearIfInvalid`(SoftDelete 参照先 → NULL / failed 参照先 → NULL / 有効参照先 → 何もしない)(REQ-default-enrollment-011, REQ-default-enrollment-012, REQ-default-enrollment-013, REQ-default-enrollment-014, REQ-default-enrollment-015, REQ-default-enrollment-016, REQ-default-enrollment-037)

## Step 9: Factory + Seeder

- [ ] `database/factories/UserFactory.php` への state 追加(任意): `withDefaultEnrollment(Enrollment $e)` state(テスト用、enrollment との連動 state)
- [ ] **Seeder 不要**: 本 Feature は cross-cutting infrastructure として `users.default_enrollment_id` カラム拡張のみであり、独自の demo データを持たない(`structure.md` Seeder 規約「⑤ 自己リソース系」分類)。既存 `UserSeeder` の `student@certify-lms.test` 固定アカウント + `EnrollmentSeeder` の demo enrollment に対し、`DefaultEnrollmentService::resolveAfterCreate` が自動的に default を設定する形で動作確認できる(EnrollmentSeeder が StoreAction 経由でデータを投入していれば自然に発火)。Seeder 側で明示的に `default_enrollment_id` を UPDATE する処理は持たない。

## Step 10: 動作確認 & 整形

- [ ] `sail artisan test --filter=DefaultEnrollment` 全件 pass
- [ ] `sail artisan test --filter=ResolveDefaultEnrollment` 全件 pass
- [ ] `sail bin pint --dirty` 整形
- [ ] 受講生フロー動作確認:
  - [ ] 受講生 `student@certify-lms.test` でログイン → サイドバー下部に Switcher が表示される
  - [ ] Switcher dropdown を開く → 受講中資格一覧が表示される
  - [ ] 現 default に青塗り「デフォルト」バッジ + ✓ チェックマーク表示
  - [ ] サイドバーの「教材」リンクをクリック → default 資格の `/learning/enrollments/{ulid}` に自動遷移
  - [ ] Switcher dropdown で別資格をクリック(単発切替) → 該当資格に遷移、`users.default_enrollment_id` 不変
  - [ ] Switcher dropdown でインアクティブな「デフォルト」バッジをクリック → 確認なしで `users.default_enrollment_id` UPDATE + 該当資格に遷移
  - [ ] `/enrollments` 画面のカード上で同じバッジ操作が可能
- [ ] フォールバック UI 動作確認:
  - [ ] `users.default_enrollment_id` を Tinker で NULL に設定 + 複数 Enrollment(`learning|passed`)を持つ受講生で `/learning` アクセス → 教材ページレイアウト内に `<x-enrollment-switcher variant="empty-state" />` が表示される(redirect されない、URL は `/learning` のまま)
  - [ ] 同状況の受講生で Enrollment 0 件にして `/learning` アクセス → 「資格カタログから申し込む」CTA 表示
  - [ ] default Enrollment を Tinker で SoftDelete → `/learning` 再アクセスで NULL リセット + 後段判定(empty-state or 0 件 CTA)
- [ ] 自動振替動作確認:
  - [ ] Enrollment 2 件持つ受講生で default A を設定 → A を `failed` 遷移 → `users.default_enrollment_id` が B に自動振替
  - [ ] Enrollment 3 件持つ受講生で default A を設定 → A を `failed` 遷移 → `users.default_enrollment_id` が NULL に戻る(残存 2 件以上)
- [ ] 初回作成動作確認:
  - [ ] Enrollment 0 件の受講生で `/certifications/{id}` から自己登録 → 1 件目作成 → `users.default_enrollment_id` が自動セット
  - [ ] 2 件目を追加 → `users.default_enrollment_id` 変更されず既存 default 保持
- [ ] アクセス制御確認:
  - [ ] coach / admin で `/settings/default-enrollment/{ulid}` に PUT → 403(`role:student` middleware)
  - [ ] `graduated` 受講生で同 PUT → 403(`EnsureActiveLearning` middleware)
  - [ ] 受講生が他者の Enrollment ID で PUT → 403(`EnrollmentPolicy::view`)
