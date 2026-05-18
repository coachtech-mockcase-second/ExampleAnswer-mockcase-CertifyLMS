# default-enrollment 要件定義

## 概要

受講生のデフォルト資格切替基盤（cross-cutting infrastructure）。`users.default_enrollment_id` ULID FK で永続化したデフォルト資格を、**サイドバー下部の固定 Switcher** + **[[learning]] / [[mock-exam]] / [[mentoring]] 予約画面のインライン Switcher** で操作する。ifield-lms の `profiles.default_genre` 設計と整合させ、**Switcher dropdown 内のバッジクリック式 2 アクション**（単発切替 / デフォルト変更+切替）を提供する。`ResolveDefaultEnrollment` Middleware が default 資格を URL に埋めて 2 階層目へ自動 redirect し、3 Feature の 1 階層目（受講中資格一覧）を廃止する。default 自動設定（初回 Enrollment 作成時 / 既存 default 無効化時の振替）+ default NULL 例外時の教材ページ内フォールバック UI（redirect しない）の 2 つの仕掛けで「教材を見たい期待」を裏切らない UX を実現する。

## ロールごとのストーリー

- **受講生（student）**: 複数の資格を同時受講中の受講生は、「いつもこの資格を見る」というデフォルト資格を Switcher dropdown 内の「デフォルト」バッジクリックで設定する。設定後はサイドバーから「教材」「模試」「面談予約」のいずれを押しても、その瞬間にデフォルト資格の 2 階層目（Part 一覧 / 模試一覧 / 空き枠検索）に直接到達できる。一時的に別の資格を見たい時は同じ dropdown 内で資格名行をクリックすれば単発切替できる（デフォルトは変更されない）。受講中資格画面（`/enrollments`）でも各カードに「デフォルト」バッジ + 「これをデフォルトにする」ボタンが表示され、同じ操作が可能。
- **コーチ（coach）**: 本 Feature の影響を受けない（コーチ向けの「資格切替」概念は別系統 = 担当資格一覧画面で個別に開く）。
- **管理者（admin）**: 本 Feature の影響を受けない。admin の画面は資格横断管理画面が主体のため Switcher を持たない。

## 受け入れ基準（EARS形式）

### 機能要件 — A. データモデル

- **REQ-default-enrollment-001**: The system shall `users` テーブルに `default_enrollment_id`（ULID, nullable, `nullOnDelete` で `enrollments.id` を参照）カラムを後追い migration（`{date}_add_default_enrollment_id_to_users_table.php`）で追加する。
- **REQ-default-enrollment-002**: The system shall `User` model に `belongsTo(Enrollment::class, 'default_enrollment_id', 'id')` リレーション `defaultEnrollment()` を提供する。
- **REQ-default-enrollment-003**: The system shall `User` model の `$fillable` に `default_enrollment_id` を追加し、ULID 文字列として保持する。
- **REQ-default-enrollment-004**: The system shall `users.default_enrollment_id` の整合性（`Enrollment.user_id === User.id`）をアプリケーション層（`DefaultEnrollmentService` + `UpdateDefaultEnrollmentRequest`）で保証する。DB 制約では「自分の Enrollment のみ」までは表現しない（ULID FK の `nullOnDelete` のみ）。

### 機能要件 — B. default 自動設定（DefaultEnrollmentService）

- **REQ-default-enrollment-010**: The system shall `App\Services\DefaultEnrollmentService` を提供し、`resolveAfterCreate(User $user, Enrollment $newEnrollment): void` / `resolveAfterStatusChange(User $user, Enrollment $changedEnrollment): void` / `clearIfInvalid(User $user): void` の 3 メソッドを公開する。
- **REQ-default-enrollment-011**: When 受講生が初回 Enrollment を作成した（受講登録 0 件 → 1 件）際, the system shall `resolveAfterCreate` で `users.default_enrollment_id` を当該 Enrollment ID にセットする。
- **REQ-default-enrollment-012**: When 受講生が 2 件目以降の Enrollment を作成（既に default 設定済）した際, the system shall `users.default_enrollment_id` を変更しない（既存 default 保持）。
- **REQ-default-enrollment-013**: When 既存 default の Enrollment が `failed` 状態に遷移 or SoftDelete された際, the system shall `resolveAfterStatusChange` を呼ぶ。
- **REQ-default-enrollment-014**: When `resolveAfterStatusChange` 実行時に当該受講生の他の `learning|passed` Enrollment が **ちょうど 1 件残存** している場合, the system shall その 1 件を新 default に自動振替する。
- **REQ-default-enrollment-015**: When `resolveAfterStatusChange` 実行時に当該受講生の他の `learning|passed` Enrollment が **2 件以上残存** している場合, the system shall `users.default_enrollment_id` を NULL に戻す。
- **REQ-default-enrollment-016**: When `resolveAfterStatusChange` 実行時に当該受講生の他の `learning|passed` Enrollment が **0 件** の場合, the system shall `users.default_enrollment_id` を NULL に戻す。
- **REQ-default-enrollment-017**: The system shall `DefaultEnrollmentService` の各メソッド呼出を `DB::transaction()` 内で実行することを呼出側（[[enrollment]] の Action / Service）に求める。本 Service 自体はトランザクションを開始しない。
- **REQ-default-enrollment-018**: The system shall [[enrollment]] の `StoreAction` から `resolveAfterCreate` を呼ぶ契約とする（依存方向: enrollment → default-enrollment）。
- **REQ-default-enrollment-019**: The system shall [[enrollment]] の `EnrollmentStatusChangeService` および `FailExpiredCommand` から `resolveAfterStatusChange` を呼ぶ契約とする。

### 機能要件 — C. ResolveDefaultEnrollment Middleware

- **REQ-default-enrollment-030**: The system shall `App\Http\Middleware\ResolveDefaultEnrollment` Middleware を提供し、`Kernel::$middlewareAliases` に `'resolve-default-enrollment'` で登録する。
- **REQ-default-enrollment-031**: The system shall 本 Middleware を `auth + role:student + EnsureActiveLearning` の後段に適用する対象ルート群を以下に限定する: (a) [[learning]] の `GET /learning` 系（1 階層目 index を含む）、(b) [[mock-exam]] の `GET /learning/enrollments/{enrollment}/mock-exams` 系の上位パス、(c) [[mentoring]] の `GET /meetings/availability` 系の予約画面。
- **REQ-default-enrollment-032**: When 適用ルートのリクエストが `{enrollment}` Route パラメータを **持たない** 場合 + `users.default_enrollment_id` が **有効**（NULL ではない、かつ参照先 Enrollment が SoftDelete されておらず status が `learning|passed`）の場合, the system shall 当該 Enrollment ID を含む URL に **302 redirect** する。
- **REQ-default-enrollment-033**: When 適用ルートのリクエストが `{enrollment}` Route パラメータを持たない場合 + `users.default_enrollment_id` が NULL の場合 + 当該受講生の `learning|passed` Enrollment が **ちょうど 1 件** の場合, the system shall その 1 件の Enrollment ID を URL に埋めて redirect する（自動 default 扱い、ただし `users.default_enrollment_id` は変更しない、ログイン中の暗黙解決）。
- **REQ-default-enrollment-034**: When 適用ルートのリクエストが `{enrollment}` Route パラメータを持たない場合 + `users.default_enrollment_id` が NULL の場合 + 当該受講生の `learning|passed` Enrollment が **2 件以上** の場合, the system shall redirect せず、そのまま Controller に処理を委譲する（フォールバック UI 表示は Controller 責務）。
- **REQ-default-enrollment-035**: When 適用ルートのリクエストが `{enrollment}` Route パラメータを持たない場合 + 当該受講生の Enrollment が **0 件** の場合, the system shall redirect せず、そのまま Controller に処理を委譲する（CTA 表示は Controller 責務）。
- **REQ-default-enrollment-036**: When 適用ルートのリクエストが `{enrollment}` Route パラメータを **持つ** 場合, the system shall Middleware では default 解決をスキップし、Route Model Binding + Policy 認可（`EnrollmentPolicy::view` 等）に処理を委譲する。
- **REQ-default-enrollment-037**: If 適用ルート進入時に `users.default_enrollment_id` が **無効**（参照先 Enrollment が SoftDelete or `failed` status）と判明した場合, then the system shall `DefaultEnrollmentService::clearIfInvalid` を呼んで NULL リセットしてから REQ-034 / REQ-035 の判定に進む。

### 機能要件 — D. Switcher Blade Component（`<x-enrollment-switcher>`）

- **REQ-default-enrollment-050**: The system shall `<x-enrollment-switcher>` Blade Component（`resources/views/components/enrollment-switcher.blade.php`）を提供する。
- **REQ-default-enrollment-051**: The system shall `<x-enrollment-switcher>` component の `variant` 属性で `sidebar` / `inline` / `empty-state` の 3 形態を出し分ける。`sidebar` は `layouts/_partials/sidebar-student.blade.php` 下部に常設、`inline` は learning / mock-exam / mentoring 予約画面の上部に埋込、`empty-state` は default NULL + Enrollment 2+ 件のフォールバック UI として main 領域に展開する。
- **REQ-default-enrollment-052**: The system shall `<x-enrollment-switcher>` の dropdown 内に当該受講生の `status IN (learning, passed)` の Enrollment を `created_at ASC` 順で表示する。
- **REQ-default-enrollment-053**: The system shall `<x-enrollment-switcher>` の dropdown の各行に以下 3 要素を表示する: (a) ✓ チェックマーク（現在閲覧中の Enrollment のみ）、(b) 資格名（`certification.name`、リンク）、(c) 「デフォルト」バッジ（現 default は青塗り・クリック不可、非 default はグレー枠・クリック可）。
- **REQ-default-enrollment-054**: When 受講生が dropdown の **資格名リンク部分** をクリックした際, the system shall 該当 Enrollment の 2 階層目 URL に GET 遷移する（単発切替、`users.default_enrollment_id` は変更しない）。
- **REQ-default-enrollment-055**: When 受講生が dropdown の **インアクティブ「デフォルト」バッジ**（グレー枠）をクリックした際, the system shall `PUT /settings/default-enrollment` を form POST で発火し、`users.default_enrollment_id` を更新後、当該 Enrollment の 2 階層目 URL に redirect する。
- **REQ-default-enrollment-056**: The system shall 現 default の「デフォルト」バッジ（青塗り）を `disabled` 属性 + `pointer-events: none` でクリック不可とする。
- **REQ-default-enrollment-057**: When 受講生の Enrollment が 0 件の場合, the system shall dropdown 内に「受講中資格がありません」テキスト + 資格カタログ（`/certifications`）への動線リンクを表示する。
- **REQ-default-enrollment-058**: The system shall `<x-enrollment-switcher>` の `variant="empty-state"` 形態で「学習する資格を選択してください」見出し + 各 Enrollment カード（資格名 + 「デフォルトバッジ」+ 単発切替リンク）を main 領域に大きく展開する。dropdown ではなくカードグリッドで表示する。
- **REQ-default-enrollment-059**: The system shall `<x-enrollment-switcher>` の dropdown 開閉を **素の JavaScript**（`resources/js/components/enrollment-switcher.js`）で実装し、Alpine.js / Livewire は採用しない（`tech.md` 準拠）。バッジクリックは form POST（form 要素を dropdown 内に配置）で完結し、JS 必須ではない（progressive enhancement）。

### 機能要件 — E. デフォルト変更 Endpoint

- **REQ-default-enrollment-070**: The system shall `PUT /settings/default-enrollment` ルートを `auth + role:student + EnsureActiveLearning` Middleware 配下に提供する。
- **REQ-default-enrollment-071**: When 受講生が本 Endpoint に `enrollment_id`（必須、ULID）を送信した際, the system shall `App\Http\Requests\UserPreference\UpdateDefaultEnrollmentRequest` でバリデーションする。
- **REQ-default-enrollment-072**: The system shall `App\UseCases\UserPreference\UpdateDefaultEnrollmentAction::__invoke(User $user, Enrollment $enrollment): User` を実行する。Action は `DB::transaction()` 内で `users.default_enrollment_id` を UPDATE する。
- **REQ-default-enrollment-073**: When Action 実行時に当該 Enrollment が `$user->id` のものでない場合, then the system shall `EnrollmentPolicy::view` の検証で `AuthorizationException`（HTTP 403）を返す。
- **REQ-default-enrollment-074**: When Action 実行時に当該 Enrollment の status が `failed` の場合, then the system shall `DefaultEnrollmentInvalidTargetException`（HTTP 422、日本語メッセージ「不合格状態の資格はデフォルトに設定できません」）で拒否する。`learning` / `passed` は通過する。
- **REQ-default-enrollment-075**: When Action 実行時に当該 Enrollment が SoftDelete されている場合, then the system shall Route Model Binding により HTTP 404 を返す。
- **REQ-default-enrollment-076**: When `UpdateDefaultEnrollmentAction` が正常終了した際, the system shall リクエストの `redirect_to` パラメータ（任意、当該 Enrollment の 2 階層目 URL）に 302 redirect する。`redirect_to` 未指定の場合は `learning.enrollments.show`（教材 Part 一覧）に redirect する。

### 機能要件 — F. 教材ページ内フォールバック UI

- **REQ-default-enrollment-080**: When 受講生が `users.default_enrollment_id = NULL` かつ `learning|passed` Enrollment 2 件以上で `/learning` index（Route パラメータなし）にアクセスした際, the system shall [[learning]] の `BrowseController::index` が `<x-enrollment-switcher variant="empty-state" />` を含む `learning/index.blade.php` を返す。redirect しない。
- **REQ-default-enrollment-081**: When 同条件で `/mock-exams` ルートにアクセスした際, the system shall [[mock-exam]] の対応 Controller が同等のフォールバック UI を表示する。
- **REQ-default-enrollment-082**: When 同条件で `/meetings/availability` ルートにアクセスした際, the system shall [[mentoring]] の対応 Controller が同等のフォールバック UI を表示する。
- **REQ-default-enrollment-083**: When 受講生が Enrollment 0 件で `/learning` index にアクセスした際, the system shall `<x-enrollment-switcher variant="empty-state" />` Component 内で「受講中資格がありません。資格カタログから申し込んでください」CTA + `/certifications` への遷移ボタンを表示する。redirect しない。
- **REQ-default-enrollment-084**: When 受講生が `empty-state` UI 内の「デフォルト」バッジをクリックした際, the system shall REQ-default-enrollment-055 と同様に `PUT /settings/default-enrollment` を発火し、当該 Enrollment の 2 階層目 URL に redirect する。
- **REQ-default-enrollment-085**: When 受講生が `empty-state` UI 内の資格名リンクをクリックした際, the system shall REQ-default-enrollment-054 と同様に単発切替（GET 遷移）する。

### 機能要件 — G. アクセス制御 / 認可

- **REQ-default-enrollment-090**: The system shall `PUT /settings/default-enrollment` ルートを `auth + role:student + EnsureActiveLearning` Middleware で保護する。
- **REQ-default-enrollment-091**: The system shall 指定された `enrollment_id` が受講生本人の Enrollment であることを `UpdateDefaultEnrollmentRequest::authorize()` 内で `EnrollmentPolicy::view` 経由で検証する。
- **REQ-default-enrollment-092**: If リクエストの `enrollment_id` が他者の Enrollment を指す場合, then the system shall HTTP 403 を返す（Policy 違反）。
- **REQ-default-enrollment-093**: The system shall coach / admin が `PUT /settings/default-enrollment` にアクセスした場合、`role:student` Middleware で HTTP 403 を返す。
- **REQ-default-enrollment-094**: The system shall `ResolveDefaultEnrollment` Middleware を coach / admin の画面ルートには適用しない（受講生のみ対象）。

### 非機能要件

- **NFR-default-enrollment-001**: The system shall `users.default_enrollment_id` の状態変更を本 Feature の `DefaultEnrollmentService` / `UpdateDefaultEnrollmentAction` 経由のみで行い、他 Feature から直接 UPDATE しない。
- **NFR-default-enrollment-002**: The system shall `ResolveDefaultEnrollment` Middleware の処理を 1 リクエストあたり 1 クエリ以下で完結させる（`auth()->user()->load('defaultEnrollment.certification')` の Eager Loading で十分）。
- **NFR-default-enrollment-003**: The system shall Switcher Component の dropdown 開閉を素の JavaScript（`resources/js/components/enrollment-switcher.js`）で実装し、Alpine.js / Livewire を採用しない。
- **NFR-default-enrollment-004**: The system shall `users.default_enrollment_id` に外部キー制約 `ON DELETE SET NULL` を付与し、Enrollment の物理削除時に自動で NULL に戻す（SoftDelete 時は別途 `DefaultEnrollmentService::clearIfInvalid` で対処）。
- **NFR-default-enrollment-005**: The system shall ドメイン例外を `app/Exceptions/UserPreference/` 配下に配置する（`DefaultEnrollmentInvalidTargetException`）。
- **NFR-default-enrollment-006**: The system shall `Enrollment` Model 側に `hasOne(User::class, 'default_enrollment_id', 'id')` の逆リレーション `defaultedByUser()` を提供する（admin 画面等での参照用、任意）。
- **NFR-default-enrollment-007**: The system shall 本 Feature の Service / Middleware / Component が依存する [[auth]] / [[enrollment]] への参照を **constructor injection** で受ける（Service Locator アンチパターン禁止、`backend-usecases.md` 規約準拠）。

## スコープ外

- **複数 default 設定**（デバイス別 / 期間別 / Feature 別）— 1 受講生 = 1 default のみ。`/learning` と `/mock-exams` でそれぞれ別の default を持つ等は採用しない
- **default 変更履歴の記録** — `user_preference_logs` のような INSERT only 監査ログテーブルは持たない。`users.default_enrollment_id` は単純な UPDATE で上書き
- **admin による他者の default 強制変更 UI** — admin が user-management 画面から受講生の default を変更する動線は持たない（本人のみ変更可）
- **default 設定の公開 API** — `/api/...` には公開しない、Web Endpoint のみ
- **coach / admin 向けの「現在のフィルタ資格」永続化** — 本 Feature は受講生のみ対象、coach の担当資格絞込等は別の機構（query parameter / セッション state）で対応
- **default を指す Enrollment の自動 SELECT 化（「最後にアクセスした資格」を自動で default に）** — 明示的なユーザー操作（バッジクリック）でのみ default 変更
- **Switcher dropdown の検索フィルタ** — 想定する Enrollment 件数は 1 受講生あたり 1〜5 件程度のため、検索は不要

## 関連 Feature

### 依存元（本 Feature を利用する）

- [[learning]] — `ResolveDefaultEnrollment` Middleware を `/learning` 系ルートに適用、`<x-enrollment-switcher variant="inline">` を 2 階層目以降の Blade に埋込、`<x-enrollment-switcher variant="empty-state">` を `learning/index.blade.php` のフォールバックで利用
- [[mock-exam]] — 同様。URL は `/learning/enrollments/{enrollment}/mock-exams` 配下に再設計（既存 spec が `/mock-exams` の場合は変更）
- [[mentoring]] — 予約画面（`/meetings/availability`）のみ Middleware 適用 + inline Switcher 埋込。履歴一覧（`/meetings`）は資格横断のため適用しない
- [[enrollment]] — `StoreAction` で `DefaultEnrollmentService::resolveAfterCreate` を呼出、`EnrollmentStatusChangeService` / `FailExpiredCommand` で `resolveAfterStatusChange` を呼出。`/enrollments` 画面で各カードに「★デフォルト」バッジ + 「これをデフォルトにする」ボタンを表示
- [[auth]] — `User` Model に `default_enrollment_id` カラム + `belongsTo(Enrollment, defaultEnrollment)` リレーション + `$fillable` 追加（本 Feature が migration を所有、`User` Model の編集のみ auth 側に追記）
- サイドバー Blade（`resources/views/layouts/_partials/sidebar-student.blade.php`）— 下部に `<x-enrollment-switcher variant="sidebar">` を常設

### 依存先（本 Feature が前提とする）

- [[auth]] — `User` モデル + `EnsureActiveLearning` Middleware + `role:student` Middleware
- [[enrollment]] — `Enrollment` モデル + `EnrollmentStatus` Enum（`learning` / `passed` / `failed`）+ `EnrollmentPolicy::view`
- [[certification-management]] — `Certification` モデル（Switcher dropdown 表示時に `enrollment.certification.name` を参照）
