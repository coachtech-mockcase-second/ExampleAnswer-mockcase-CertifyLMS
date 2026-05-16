# Certify LMS 実装品質メタ監査

> 監査対象: 実装済み 4 Feature (`auth` / `user-management` / `certification-management` / `content-management`) + 全 18 Feature spec の実装方針
> 監査日: 2026-05-16
> 監査基準: Laravel 業界標準 / 実務水準 / `.claude/rules/` 規約

## 2026-05-16 4 判断による update（本監査後に確定）

本監査の「規約 vs 実装の乖離方針判断」（末尾「規約 vs 実装の乖離方針判断」セクション）を受けて、**A 案（規約に実装を合わせる）を採用**することがユーザー承認のもと確定。以下の通り反映:

### P0 アクション（段階 2 で実施予定）

| # | 対象 | アクション | ステータス |
|---|---|---|---|
| P0-1 | `pint.json` 不在 | 模範解答PJ / 提供PJ のルートに `pint.json` 新設、`declare_strict_types: true` rule 有効化 | **段階 2 で着手** |
| P0-2 | `declare(strict_types=1)` 全 0 件 | Pint 設定後 `sail bin pint` 一括実行で 208 ファイルに付与 | **段階 2 で着手** |
| P0-3 | `private readonly` 規約不徹底 | 全 Action / Service の DI コンストラクタを `private readonly` に修正（約 10 ファイル） | **段階 2 で着手** |
| P0-4 | `HasApiTokens` trait 残置 | `App\Models\User` から削除、`personal_access_tokens` migration も削除（**Sanctum SPA 全体撤回に連動**） | **段階 2 で着手** |
| P0-5 | `tests/{Feature,Unit}/ExampleTest.php` 残置 | 削除 | **段階 2 で着手** |

### 規約 A 案の実装方針

- **新規 Feature**: `backend-types-and-docblocks.md` が `paths` frontmatter で auto-load される設計に従い、`feature-implement` Skill の「Step → 主参照 rules マップ」に PHP 系 Step すべてへ追加。Skill が自動準拠する
- **既存 4 Feature**: 段階 2 で一括バッチ修正（Skill ではなく独立 Wave として実施）
- **コメント方針**: `backend-types-and-docblocks.md` の「コメントの 4 層」（クラス DocBlock 必須 / メソッド DocBlock 自明でない場合必須 / 行内 Why のみ / TODO・FIXME・NOTE タグ）を「実務範囲内で多めに」運用

### Sanctum SPA / 公開 API / Resource クラス撤回（2026-05-16）

本監査 P1-5「Repository パターン初実装」「Resource パターン初実装」のうち、**Resource パターン** は LMS 全体で不採用方針となった（[[analytics-export]] も Resource を持たず Eloquent Resource 経由のシンプル配列で返却する設計に統一）。これは Sanctum SPA / 自前 FE SPA / 公開 JSON API を LMS 全体で持たない決定に連動:

- [[quiz-answering]]: 公開 API / Sanctum SPA / JS Ajax / Resource 全撤回 → Blade + Form POST + Redirect 統一
- [[learning]]: `session-tracker.js` / `sendBeacon` 撤回 → サーバ側 auto-start + 明示停止ボタン + Schedule Command 統一
- [[analytics-export]]: API キー方式は維持（Resource クラスは採用せず、軽量な JSON 整形で十分）

**Repository パターン初実装**（[[ai-chat]] / [[mentoring]] で `GeminiRepository` / `GoogleCalendarRepository`）は計画通り維持。外部 API 連携の依存切り離しとして `backend-repositories.md` 規約通り実装。

### UserStatusLog event_type 追加（2026-05-16）

本監査では明示言及なしだが、`feature-data-models.md` 監査での「監査ログフォーマット統一」決着を受けて、`UserStatusLog` に `event_type` カラム + `UserStatusEventType` enum を追加。`UserPlanLog.event_type` とフォーマット統一（[[user-management]] / [[plan-management]] design.md 反映済み）。

---

## エグゼクティブサマリ

本プロジェクトの **実装は総じて Laravel 業界標準を高いレベルで満たしており**、Pro 生候補のための教材として十分な品質を確保している。Eloquent の `HasUlids` / `SoftDeletes` / `$casts` (Enum) / `scope` メソッドの徹底活用、FormRequest による責務分離、Policy ベースの認可、Action(UseCase) パターンによるトランザクション境界の明示、`DB::afterCommit` による Storage 副作用の正しい後置、`lockForUpdate` による採番競合対策、ULID 主キーと適切な複合 index など、ベテラン Laravel エンジニアが書く実装に近い。テスト戦略も `Mail::fake` / `Storage::fake` の使い分け、Feature と Unit の役割分離、必須シナリオ(認可漏れ / バリデーション失敗 / 状態遷移境界) のカバレッジが堅実。

一方で、`.claude/rules/backend-types-and-docblocks.md` で「必須」と明記している規約が **実装に反映されていない箇所が散見** される。最大の問題は (1) `declare(strict_types=1)` が 208 ファイル中 0 件、 (2) `private readonly` が Action / Service のコンストラクタプロモートに 8 件しか適用されておらず、`UserStatusChangeService` / `IssueInvitationAction` を含む主要 Action / Service が `private` のみ(`readonly` なし)で書かれている、 (3) `@param array{...}` の shape annotation が一切なく、 (4) `@throws` が独自例外を投げる Action にほぼ付与されていない、 (5) `pint.json` が存在せず Pint が `declare_strict_types` を自動付与できる前提が成立していない、の 5 点。これらは実装の動作品質には影響しないが、教材としての「Pro 生に身につけさせる型意識」「DocBlock の補完責務」「IDE による DocBlock ジャンプの恩恵」を提示できないため、規約と実装の乖離を **規約側に寄せる** か **実装を底上げする** かの方針判断を要する。

セキュリティ面は良好で、Mass Assignment 保護 ($fillable 厳格)、CSRF、認可、生 SQL 不使用、ファイルアップロード検証、署名付き URL、private/public ディスク分離など、Basic 範囲で押さえるべき要素は揃っている。パフォーマンス面も `withCount` / `with` Eager Loading / 複合 index / `lockForUpdate` / `DB::afterCommit` を適切に使い分けており、N+1 リスクは確認した範囲では見当たらない。Resource クラスは Basic スコープ外なので未採用だが、Advance の `analytics-export` spec ではしっかり採用されている。Notification / Mail は Basic で `Mailable + Markdown` を、Advance で本格的な `Notification + Database + Mail channel` を採用する設計で、段階的習熟が考慮されている。

## 観点 1: Laravel 慣習の活用度

### 強み

**Eloquent best practice の徹底**:
- `App\Models\Certification` (`/Users/yotaro/ExampleAnswer-mockcase-CertifyLMS/模範解答プロジェクト/app/Models/Certification.php:100-125`) — `scopePublished` / `scopeAssignedTo` / `scopeKeyword` が再利用クエリ条件として整理され、Controller / Action 側のロジックを Model に押し付けない清潔な構造
- `App\UseCases\Certification\IndexAction` (`同/app/UseCases/Certification/IndexAction.php:19-21`) — `withCount(['coaches', 'certificates'])` + `with('category')` で一覧表示の N+1 を予防、`paginate()->withQueryString()` でクエリ保持
- `App\UseCases\Question\IndexAction` (`同/app/UseCases/Question/IndexAction.php:21-25`) — `with(['section.chapter.part', 'category', 'options' => fn ($q) => $q->ordered()])` でネストリレーション + クロージャ Eager Loading
- 状態カラムは全件 PHP Enum で `$casts` (`Certification.php:37-44`, `User.php:39-46`)。マジック文字列を排除している
- `withTrashed` + `withdraw()` メソッド + `softDeletes()` migration の三点セットで論理削除を一貫運用 (`User.php:95-103`, `UserController.php`, `Invitation.php:39`)

**FormRequest の徹底**:
- 全ての state 変更 Controller method (store / update / destroy / publish / archive 等) に対応する `Http\Requests\{Entity}\{Action}Request` が存在 (全 28 FormRequest クラスを確認)
- `authorize()` で Policy を呼ぶラッパー実装が一貫 (例: `Http\Requests\Invitation\StoreRequest:12`, `Http\Requests\User\UpdateRoleRequest:11`)
- `attributes()` で日本語化、`rules()` の Enum 値展開 (`Http\Requests\Question\StoreRequest:26`) も適切

**Policy の活用度**:
- 全 12 リソースに Policy が定義され `AuthServiceProvider:38-51` で登録
- `match ($auth->role)` パターンで Admin/Coach/Student の認可分岐が読みやすい (`Policies\QuestionPolicy:13-39`)
- Coach の担当資格チェック (`assignedCoach()` private method) を Policy 内に閉じ込めている
- Controller で `$this->authorize()` または FormRequest の `authorize()` で必ず呼ばれる二段構え

**Route Model Binding + リソースルート**:
- `Route::resource('certifications', ...)` (`routes/web.php:89-91`)、`parameters()` / `names()` で命名管理
- `withTrashed()` を `admin.users.show` のみ付与し、退会済表示シナリオを許容しつつデフォルトは soft delete を尊重 (`routes/web.php:78`)

**Middleware の使い分け**:
- `EnsureUserRole` (`app/Http/Middleware/EnsureUserRole.php`) は **ロール存在確認のみ** に徹し、リソース固有認可は Policy に委譲(規約通り)
- `role:admin,coach` の可変長引数で複数ロール許可、Stripe 署名検証 / 招待 URL 検証は `signed` middleware + `URL::temporarySignedRoute` で標準機能を使う (`OnboardingController:38-42`)

**Notification / Mail / Schedule Command**:
- `Mailable + Markdown view` パターン (`Mail\InvitationMail.php`) で `markdown: 'emails.invitation'` 採用
- `ResetPasswordNotification` (`Notifications/Auth/ResetPasswordNotification.php`) は `Illuminate\Auth\Notifications\ResetPassword` を継承して件名/本文を日本語化(Laravel 標準の正しい拡張パターン)
- `Console\Kernel.php:17` の `$schedule->command('invitations:expire')->dailyAt('00:30')` — Schedule Command を Action に委譲する薄い設計
- `EventServiceProvider` 経由で `Login` イベントに `UpdateLastLoginAt` を Listener 登録 (`app/Listeners/UpdateLastLoginAt.php`) — Observer ではなく Event/Listener を選択しているのは責務が User Model 内部の状態同期ではなく「ログインイベントへの副作用」のため適切

**Service Container DI**:
- 全 Controller / Action が constructor / メソッド DI を一貫採用 (`app/UseCases/Auth/IssueInvitationAction.php:19-23`)
- 明示的な `$this->app->bind()` を `AppServiceProvider` で書く必要のあるケースは現状なし(Laravel 自動解決の範囲内)

### 問題点

**問題 1: Resource クラス未採用 (現時点では妥当だが先送り注意)**
- 現状 4 Feature では JSON API を提供せず、Blade view に Model を直接渡しているため Resource 不要
- `analytics-export` spec (`docs/specs/analytics-export/design.md:140-220`) で `UserResource` / `EnrollmentResource` / `MockExamSessionResource` を採用予定
- 改善提案: Advance の `analytics-export` 実装時に Resource 命名規約 (`app/Http/Resources/Api/Admin/`) を確立して、`SectionImageController::store` (`SectionImageController.php:14-23`) の手書き JSON response (`['id' => ..., 'url' => ...]`) も `SectionImageResource` に統一すべき。現状の手書き JSON はマイクロ規模だが、似た箇所が増えると一貫性が損なわれる

**問題 2: Repository 層が定義のみ、まだ実体なし**
- `backend-repositories.md` で「Gemini / GoogleCalendar / Pusher の依存切り離し」と明記されているが、現実装 4 Feature には外部 API が存在せず Repository も未実装
- 改善提案: `ai-chat` / `mentoring` / `chat` 実装フェーズで規約通りの Interface + Implementation を導入し、テストで `Http::fake()` ではなく Interface モックを使えるようにする(Anthropic / Google ライブラリの SDK 直 mock は脆い)

**問題 3: Observer 未採用箇所(段階的にしか影響しない)**
- `User::withdraw()` (`User.php:95-103`) は Service / Action から呼ばれる前提だが、`UserStatusLog` 記録を呼出側で必ず実施しなければならない契約が暗黙
- 改善提案: Phase 5 (集計依存 Feature) 以降、`StatusLogged` Event + `UserObserver` を導入して、`User::status` UPDATE 時に必ず log が記録されるようにする。現状 Action 側で漏れなく `$statusChanger->record()` を呼んでいるが、`force=true 再招待` (`IssueInvitationAction:74`) では意図的に log を省略するなど契約が分散しており追跡が難しい

**問題 4: View Composer の Scope 限定**
- `AppServiceProvider.php:18` で `View::composer('layouts._partials.sidebar-*', SidebarBadgeComposer::class)` を登録しているが、サイドバーバッジが計算負荷の高いクエリだった場合 N+1 リスク
- 改善提案: `SidebarBadgeComposer` 内のクエリを実機で計測し、必要なら `Cache::remember()` で 1 分キャッシュを掛ける。Basic スコープなのでこれは Phase 2 以降の課題

## 観点 2: セキュリティ

### 強み

**Mass Assignment 保護**:
- 全 Model に `$fillable` 明示 (`User.php:21-32`, `Certification.php:20-35`, `Question.php:19-29`)
- `forceFill()` は 12 件あるが、**全件が「mass assignment 不可フィールドの意図的な書き換え」**(`status` / `password` / `accepted_at` / `revoked_at` / `last_login_at`) で、`$fillable` 漏れではなく意図的なバイパス
- `User::withdraw()` (`User.php:95-103`) の `forceFill(['email' => ..., 'status' => ...])` は **`fillable` に含まれているが、ULID リネームの瞬間整合性を担保するために `forceFill` を使う**理由が明確 (DB Listener / Observer 経由の意図せざる変換を回避)
- 評価: `forceFill` の使用が「禁止技」ではなく「契約付き例外」として運用されており、コメントで意図が説明されている (`User.php:91-94`) のは教材として優れている

**CSRF / XSS**:
- Web ルートは Laravel デフォルトの `web` middleware group で `VerifyCsrfToken` が有効 (`Http\Kernel.php:55-63`)
- Blade 出力は `{{ }}` エスケープが基本で、`@csrf` ディレクティブも `frontend-blade.md` で必須として規約化
- `Services\MarkdownRenderingService` (`app/Services/MarkdownRenderingService.php:21-32`) は `html_input => 'strip'` + `allow_unsafe_links => false` + 画像 URL の allowlist (`isAllowedImageUrl`) + 外部リンクの `target=_blank` + `rel=noopener noreferrer` を全て実装。CommonMark の XSS 対策のベストプラクティス
- 教材 (`Section.body`) は受講生向けにレンダリングされる Markdown なので XSS 対策の徹底度合いが特に重要

**SQL Injection**:
- 生 SQL (`DB::raw` / `whereRaw` 等) は **`orderByRaw` 2 箇所のみ** (`User\IndexAction:42`, `Certification\IndexAction:39`)、いずれも固定リテラル文字列でユーザー入力は混入しない
- `LIKE` 検索は `'%'.$keyword.'%'` の placeholder バインドで MySQL/PostgreSQL の prepared statement に渡る (`User\IndexAction:25-28`, `Certification.php:121-125`)

**認可漏れ防止**:
- Controller method で `$this->authorize()` を呼ぶか、FormRequest の `authorize()` で Policy を呼ぶかの二段構え
- 確認した中で漏れがある可能性のある箇所: `ContentSearchController::search` (`app/Http/Controllers/ContentSearchController.php`) は明示的な `$this->authorize()` がないが、`SearchAction` 内で `$student->enrollments()->where('certification_id', ...)->exists()` で当人の登録確認 (`SearchAction.php:32-39`) を行っており、結果的に他資格の検索を拒否する。Policy としては未実装だが Action 内のデータ整合性チェックで代替している
- 改善提案: `ContentSearchPolicy` を作って `viewByCertification(User $user, string $certificationId)` のような form で表現すれば、認可ロジックが Action から Policy に切り出されて読みやすくなる(現状でもセキュリティは確保されている)

**Signed URL の活用**:
- `OnboardingController` (`app/Http/Controllers/Auth/OnboardingController.php:23-27, 35-42`) で `URL::temporarySignedRoute` を使い、`onboarding.store` ルートに `signed` middleware を付与
- `InvitationTokenService::verify` (`app/Services/InvitationTokenService.php:30-50`) で署名検証 + Invitation 状態 + User 状態の三重チェック
- 評価: 招待 URL の改竄耐性が業界標準水準

**ファイルアップロード**:
- `Http\Requests\SectionImage\StoreRequest` (`StoreRequest.php:20-24`) で `mimes:png,jpg,jpeg,webp` + `max:2048` を必須化
- `UseCases\SectionImage\StoreAction` (`StoreAction.php:18-26`) で ULID ベースのファイル名生成、`public` disk に分離保存
- 失敗時の Storage ロールバック (`SectionImageStorageException` 発生時に `Storage::disk('public')->delete($path)` で巻き戻し、`StoreAction.php:35-38`)
- 評価: 拡張子と MIME の二重検証、決定論的ファイル名による衝突回避、トランザクション失敗時のロールバックという三点が揃っている

**プライベートストレージ配信**:
- `CertificateController::download` (`CertificateController.php:22-27`) は `$this->authorize('download', $certificate)` 後に `DownloadAction` を呼び `Storage::disk('private')->download(...)` (`DownloadAction.php:14-25`)
- 修了証 PDF は private disk に保存され、URL 直打ちで取得不可。Policy + Controller 認可で初めて配信される
- 評価: private 配信は教材として完成度が高い

**.env / config の網羅**:
- `.env.example` (`模範解答プロジェクト/.env.example`) を確認、`APP_NAME` / `COMPOSE_PROJECT_NAME` / `APP_PORT` (8000) / `VITE_PORT` (5174) など BookShelf 並走を考慮した設定が完備
- Mailpit 接続 (`MAIL_HOST=mailpit:1025`) も `tech.md` の Mailpit URL と整合
- 改善提案: 後続 Feature で `GEMINI_API_KEY` / `GOOGLE_OAUTH_CLIENT_ID` / `PUSHER_APP_KEY` / `ANALYTICS_API_KEY` 等を追加する際は `.env.example` への記載と `config/services.php` / `config/analytics-export.php` の二段管理を徹底すべき

### 問題点

**問題 1: API レート制限の不明確さ**
- 現状 4 Feature では公開 API がなく問題化していないが、`analytics-export` spec (`docs/specs/analytics-export/design.md:20`) で `throttle:60,1` を予定
- `Http\Kernel.php:67` の `ThrottleRequests::class.':api'` は Laravel 標準だが、デフォルト `api` 限定値が `60/min` であることをコード上に明示する config が未整備
- 改善提案: `analytics-export` 実装時に `RouteServiceProvider::configureRateLimiting()` で `RateLimiter::for('api', ...)` を明示し、レート制限のロジックを spec の通り集中管理

**問題 2: ファイルサイズ・拡張子検証の `2048KB` の根拠**
- `SectionImage\StoreRequest:24` の `max:2048` は KB 単位で 2MB 相当だが、教材画像として適切か?
- 改善提案: `config/section-images.php` 化して `'max_size_kb' => env('SECTION_IMAGE_MAX_SIZE_KB', 2048)` で外出し、運用調整可能に。教材としては「マジックナンバーは config に出す」習慣を養成

**問題 3: User の `HasApiTokens` trait 残置**
- `App\Models\User` (`User.php:15, 19`) で `Laravel\Sanctum\HasApiTokens` を `use` している
- `tech.md:18` で Personal Access Token は不採用、Sanctum SPA Cookie ベースのみ採用と明記
- `HasApiTokens` は Personal Access Token 機能を提供するが、Cookie ベース SPA 認証には不要
- 改善提案: `HasApiTokens` を User Model から削除。`personal_access_tokens` テーブル migration (`2019_12_14_000001_create_personal_access_tokens_table.php`) も Laravel デフォルト残置だが、実際には使わないのでマイグレーション削除も検討

**問題 4: `ContentSearchController` の認可表現が暗黙**
- 上記「強み」セクションで触れた問題で、`ContentSearchPolicy` がなく Action 内チェックに依存
- 受講生が `?certification_id={他資格ID}` で URL を叩いても、`SearchAction:32-39` で空結果が返るのみ。403 ではなく空結果を返す挙動は UX 的には妥当だが、教材的には「認可は Policy で明示する」原則と乖離

## 観点 3: パフォーマンス

### 強み

**N+1 対策**:
- `Certification\IndexAction:19-21` で `with('category')` + `withCount(['coaches', 'certificates'])` を一括 Eager Loading
- `Question\IndexAction:21` で `with(['section.chapter.part', 'category', 'options' => fn ($q) => $q->ordered()])` の深いネスト + クロージャ
- `User\ShowAction:11-17` で `statusLogs.changedBy` (withTrashed) と `invitations.invitedBy` (withTrashed) を同時 Eager Loading + ソート指定
- 評価: List 系の N+1 リスクは確認した範囲で見当たらない

**Index 設計**:
- `certifications` テーブル — `['status', 'category_id']` 複合 + `deleted_at` (一覧フィルタの典型クエリ最適化)
- `enrollments` テーブル — `['user_id', 'status']` 複合 + `certification_id` (受講生別の受講中資格抽出)
- `questions` テーブル — `['certification_id', 'status']` + `['certification_id', 'difficulty']` + `section_id` + `category_id` + `deleted_at` (5 index、出題条件絞り込み)
- `invitations` テーブル — `['status', 'expires_at']` (Schedule Command `invitations:expire` の高速化目的)
- `user_status_logs` テーブル — `user_id` + `changed_by_user_id` + `changed_at` (履歴照会の縦横断アクセス対応)
- 評価: 複合 index の選択が業務クエリと整合しており、業界標準的に「足りすぎ」も「足りなさすぎ」もない

**Cache 戦略**:
- 現状 4 Feature では `Cache::` 呼出ゼロ
- `tech.md:30` で「キャッシュ」を Advance 範囲と位置付け
- 評価: Basic スコープでは適切。`ai-chat` spec (`docs/specs/ai-chat/design.md:456`) で `RateLimiter::for('ai-chat', ...)` + `Cache` の組み合わせを採用予定で、これは業界標準

**トランザクション境界**:
- 全 Action が `DB::transaction(fn () => ...)` で囲む(規約通り)
- 状態変更を伴わない Read-Only Action (`Certification\IndexAction`, `Question\IndexAction`, `User\ShowAction` 等) はトランザクションなし(過剰使用なし)
- `Certificate\IssueAction:34-57` のように、外部I/O (PDF生成) を含む処理でも DB::transaction 内で実行 → これは PDF 生成失敗時に Certificate INSERT もロールバックされる契約を意図しているが、PDF 生成失敗時の Storage 削除は実装されていない
- 改善提案: `IssueAction:53` の `$this->pdfGenerator->generate($certificate)` を `DB::afterCommit` に追い出すか、try-catch で Storage::delete を巻き戻すか方針判断が必要。現状は失敗時に「Certificate INSERT は巻き戻る、PDF ファイルは残る」状態

**`DB::afterCommit` の活用**:
- `SectionImage\DestroyAction:16` — `DB::afterCommit(fn () => Storage::disk('public')->delete($path))` で DB 削除コミット後に Storage 削除
- 評価: Storage 削除を `afterCommit` に置く判断は業界標準。失敗時の状態を「DB に row 残ったまま Storage は消えた」(orphan)にしないための正しい順序

**`lockForUpdate` 採番競合対策**:
- `Section\StoreAction:16` / `Chapter\StoreAction:16` / `Part\StoreAction:16` — `lockForUpdate()->max('order')` で並列 INSERT 時の order 衝突を回避
- `Certificate\IssueAction:35-38` — `Certificate::lockForUpdate()->where('enrollment_id', $enrollment->id)->first()` で同一 Enrollment への二重発行を防ぐ
- `CertificateSerialNumberService:21` — `'serial_no' LIKE 'CT-{YYYYMM}-%'` + `lockForUpdate` で月次連番採番の競合対策
- 評価: 採番系で `lockForUpdate` を意識的に使えるのは Pro 生レベル

### 問題点

**問題 1: `Certificate\IssueAction` の PDF 生成と transaction の組み合わせ**
- 上記「トランザクション境界」で言及した通り、PDF 生成失敗時の Storage rollback が未実装
- 改善提案: 以下のパターンに修正
  ```php
  $certificate = DB::transaction(function () use ($enrollment, $admin) {
      // ... Certificate::create ...
      return $certificate;
  });
  // commit 後に PDF 生成、失敗時は別ハンドリング
  try {
      $this->pdfGenerator->generate($certificate);
  } catch (\Throwable $e) {
      $certificate->delete(); // soft delete
      throw new CertificatePdfGenerationFailedException($e);
  }
  ```
  もしくは `DB::transaction` 内で PDF 生成を行いつつ `try/catch` で失敗時に `Storage::disk('private')->delete($certificate->pdf_path)` を呼ぶ

**問題 2: 一覧クエリでの `orderByRaw` 採用 (MySQL 特化リスク)**
- `User\IndexAction:39-47` / `Certification\IndexAction:37-45` で MySQL の `FIELD()` 関数を使い、それ以外の driver (sqlite 等) では `CASE WHEN` フォールバック
- 評価: テスト時に sqlite を使う場合のフォールバックが書かれているのは丁寧だが、本番 MySQL 専用なら `orderByRaw` を 1 種類に絞っても良い。「driver 判定して切替」のロジックは教材的には冗長
- 改善提案: `config('database.default')` を見るのではなく、`Eloquent` の `Builder` 拡張マクロを `AppServiceProvider` で定義する手もある (やや上級)

**問題 3: `ContentSearch\SearchAction` の `LIKE '%keyword%'` 検索**
- `SearchAction:42-59` — `'title LIKE %keyword% OR body LIKE %keyword%'` で全件スキャン (index 効かない)
- `sections.title` には index あり (`migrations/2026_05_14_000032_create_sections_table.php:27`) だが `LIKE '%...'` の prefix wildcard では使えない
- 教材コンテンツが数千件以上の規模になると遅くなる
- 改善提案: Basic 範囲では問題ないが、Advance または将来拡張で `MATCH ... AGAINST` (MySQL Full-Text) または `Scout + Meilisearch` を検討。spec に「Basic では `LIKE`、Advance で Full-Text 検索」のアップグレードパスを書く価値あり

**問題 4: chunk / lazy 未採用**
- 現状 4 Feature の List 系はすべて `paginate(20)` (20件/page) なので問題なし
- `ExpireInvitationsAction:26-44` は `Invitation::expired()->get()` でメモリに全件ロード後 foreach
- 改善提案: 期限切れ Invitation が極端に多い (数万件以上) 状況は通常発生しないが、Schedule Command で大量処理する場合は `lazy()` または `chunkById(500)` を使うべき。教材としては「Schedule Command は chunk するパターン」を 1 箇所で示すと学習価値高

## 観点 4: テスト戦略

### 強み

**Coverage 戦略の妥当性**:
- Feature テスト 47 件 / Unit テスト 16 件 / Support helpers 1 件 = 計 64 件
- 配置規約 (`backend-tests.md:19-29`) に正確に従う:
  - `Feature/Auth/{Flow}Test.php` — 認証フロー
  - `Feature/Http/Admin/{Entity}/{Action}Test.php` — Controller 単位
  - `Feature/UseCases/{Entity}/{Action}ActionTest.php` — Action の複雑ケース
  - `Unit/Services/`, `Unit/Policies/`, `Unit/Models/`, `Unit/Middleware/`
- 評価: Feature/Unit の役割分離が明確。Controller の E2E は Feature テスト、ロジック単体は Unit テスト

**Mock 戦略**:
- `Mail::fake()` を Mailable 検証に使用 (`tests/Feature/Http/Admin/Invitation/StoreTest.php:20, 46`)
- `Storage::fake('public')` / `Storage::fake('private')` で Storage アクセスの分離 (`tests/Feature/Http/Admin/SectionImage/StoreTest.php:19`, `tests/Feature/Http/Certificate/DownloadTest.php:17`)
- `Notification::fake()` でパスワードリセット通知の検証 (`tests/Feature/Auth/PasswordResetTest.php:20`)
- 評価: Laravel が提供する fake 系の使い分けが正確

**Factory 活用**:
- `User::factory()->admin()->create()` / `coach()` / `student()` / `invited()` の state pattern (`tests/Feature/Http/Admin/User/IndexTest.php:14-37`)
- `Certification::factory()->published()->create()` / `draft()` / `archived()` (`tests/Unit/Policies/CertificationPolicyTest.php:36-39`)
- `Question::factory()->forCertification($cert)->forCategory($category)->withOptions(4)->draft()->create()` のチェーン (`tests/Feature/Http/Admin/Question/CrudTest.php:89-94`)
- 評価: state + for 系のチェーンで意図が明確、テストデータ構築が短く読める

**Test Trait の共有**:
- `tests/Support/ContentTestHelpers.php` — `makeCategory($cert)` のような汎用ファクトリヘルパを集約
- `tests/TestCase.php` / `tests/CreatesApplication.php` でセットアップ統一

**RefreshDatabase + actingAs パターンの徹底**:
- ほぼ全 Feature テストで `use RefreshDatabase;` + `$this->actingAs($user)` が冒頭に登場
- DB の状態を毎テストでリセット、認証コンテキストを明示

**必須テストパターンのカバレッジ**:
- 認可漏れ — `UpdateRoleTest::test_admin_cannot_change_own_role` (`UpdateRoleTest.php:31-44`)、`IndexTest::test_coach_and_student_cannot_access_admin_users_index` (`IndexTest.php:26-38`)
- バリデーション失敗 — `Question\CrudTest::test_store_rejects_zero_correct_options` (`CrudTest.php:45-62`)
- 状態遷移境界 — `Question\CrudTest::test_publish_requires_valid_options` (`CrudTest.php:116-137`)
- ロール固有機能 — `CertificationPolicyTest::test_coach_and_student_can_only_view_published_certifications` (`CertificationPolicyTest.php:41-57`)
- メール発火 — `IssueInvitationActionTest::test_dispatches_invitation_mail` (`IssueInvitationActionTest.php:104-112`)
- 状態ログ記録 — `IssueInvitationActionTest::test_inserts_user_status_log_with_invited_status_on_new_user_insert` (`IssueInvitationActionTest.php:114-127`)
- 副作用なしの確認 — `UpdateRoleTest::test_does_not_insert_user_status_log_on_role_change` (`UpdateRoleTest.php:46-57`)
- 評価: 「Pro 生として最終評価される最後の関門」に必要なテストパターンが網羅されている

### 問題点

**問題 1: Feature テスト命名の不揃い**
- 多くが `{Entity}/{Action}Test.php` (例: `Admin\Invitation\StoreTest.php`) だが、一部 `Admin\Chapter\CrudTest.php` のように「複数アクションを 1 ファイル」のパターンがある
- 改善提案: 教材として規約を統一するか、複数アクションをまとめる基準を明示する (例: 「CRUD 5メソッド全部に固有テストが必要」or「Crud は 1 ファイル可」)。受講生が真似する際に判断に迷う

**問題 2: Unit テストの偏り**
- Service テストは充実 (5 件: `CertificatePdfGenerator`, `CertificateSerialNumber`, `InvitationToken`, `MarkdownRendering`, `UserStatusChange`)
- Policy テストは 6 件
- Model のスコープテストは `CertificationScopesTest` のみ
- 改善提案: `Question::scopePublished` / `scopeBySection` / `scopeStandalone` / `scopeByCategory` / `scopeDifficulty` (5 つのスコープ) の Unit テストがあると、受講生に「Eloquent scope は Unit テストする」感覚が伝わる

**問題 3: Repository テストパターンの未実装**
- `Http::fake()` で外部 API をスタブ化するテストパターンの実例がまだない (Repository 未実装のため当然)
- `backend-repositories.md:64-67` で `Http::fake()` を要求しているが、実例コードがない
- 改善提案: 後続 `ai-chat` / `mentoring` 実装時に `tests/Unit/Repositories/GeminiRepositoryTest.php` を最初に書いて、`Http::fake([...])` で正常系 + エラーレスポンス系を網羅する模範例とする

**問題 4: 同時実行テスト未網羅**
- `lockForUpdate` を使う `CertificateSerialNumberService::generate` / `Certificate\IssueAction` (冪等性) のテストは存在するが、複数プロセスからの同時呼出を検証する DB 競合テストはない
- 改善提案: Basic スコープでは不要だが、Advance テスト戦略として `pcntl_fork` ベースの並列発行テストを示すと Pro 生レベルへの橋渡しになる

**問題 5: ExampleTest の残置**
- `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php` が削除されずに残っている
- 教材としては削除すべき (Laravel デフォルトの「お祝い」テスト)

## 観点 5: エラー設計

### 強み

**Exception 階層の妥当性**:
- ドメイン例外を `app/Exceptions/{Domain}/` に配置 (Auth/Certification/Content/UserManagement)
- Symfony HTTP 例外 (NotFoundHttpException / ConflictHttpException / AccessDeniedHttpException) を継承する規約通りの実装
- 例: `EmailAlreadyRegisteredException` → `ConflictHttpException` (HTTP 409)、`CertificationNotFoundException` → `NotFoundHttpException` (HTTP 404)、`InvalidInvitationTokenException` → ...

**HTTP status の使い分け**:
- 404 (リソース未存在): `EnrollmentNotPassedException`, `CertificatePdfNotFoundException`
- 409 (状態競合): `CertificationInvalidTransitionException`, `ContentInvalidTransitionException`, `QuestionNotPublishableException`, `InvitationNotPendingException`, `EmailAlreadyRegisteredException`, `PendingInvitationAlreadyExistsException`
- 422 (バリデーション境界): `QuestionCategoryMismatchException`, `QuestionInvalidOptionsException` (Action 内の整合性チェック)
- 403 (認可エラー): `SelfRoleChangeForbiddenException`, `SelfWithdrawForbiddenException`
- 評価: HTTP セマンティクスへの認識が正確

**ドメイン例外の表現力**:
- `ContentInvalidTransitionException` (`app/Exceptions/Content/ContentInvalidTransitionException.php:10-25`) — `public readonly string $entity`, `public readonly ContentStatus $from`, `public readonly ContentStatus $to` を保持し、エラーメッセージに「Question の現在の状態(下書き)からはこの操作(公開)を行えません」と動的生成
- `CertificationInvalidTransitionException` (`app/Exceptions/Certification/CertificationInvalidTransitionException.php:10-23`) — `public readonly CertificationStatus $from`, `to` を保持
- 評価: 状態遷移情報を例外オブジェクトに持たせる設計は、後段の API レスポンス整形 / ログ出力 / テスト assert で活用できる業界標準パターン

**Handler.php の整備**:
- `app/Exceptions/Handler.php` は Laravel 10 デフォルト構造を維持し、`$dontFlash` で password 系のセッション flash を防御
- `reportable` callback は空 (将来 Sentry / Bugsnag を追加する余地)
- 評価: 現状 Basic 範囲では十分。Advance で `renderable` を追加して 401/403/404/422/500 の JSON / Blade 切替を整理する余地

**log 戦略**:
- Schedule Command 内で `$this->info("期限切れ Invitation を {$count} 件処理しました。")` (`ExpireInvitationsCommand:18`) でコンソール出力
- Action 内では `Log::error()` 呼出なし(規約上、例外を throw する責任は Action、log 記録は Handler の責務という設計)
- 評価: Basic スコープでは十分。Advance で Repository (`GeminiRepository`) から例外を Action に投げ返す際に Handler 側で `Log::error()` を入れるパターンが必要

### 問題点

**問題 1: `WithdrawAction` で `HttpException` (汎用) を投げている箇所**
- `app/UseCases/User/WithdrawAction.php:29-31`:
  ```php
  if ($user->status === UserStatus::Invited) {
      throw new HttpException(422, '招待中ユーザーは「招待を取消」から削除してください。');
  }
  ```
- `backend-exceptions.md:55-57` で「汎用 `\Exception` は避ける」と明記、これは Symfony の `HttpException` 直接 throw だが、ドメイン例外として独立クラス化されていない
- 改善提案: `App\Exceptions\UserManagement\InvitedUserWithdrawNotAllowedException extends UnprocessableEntityHttpException` を作って throw すべき。現状はテストでメッセージ文字列を assert する必要があり脆い

**問題 2: `@throws` 宣言が圧倒的に不足**
- `app/UseCases/` 配下の Action で独自例外を投げるメソッドは 20 以上あるが、`@throws` 宣言があるのは `Certificate\IssueAction.php:26-28` のみ (1 件)
- Fortify 系の 4 件 (`Actions/Fortify/*.php`) は `@throws ValidationException` を書いているが、これは Fortify scaffolding のデフォルト
- 改善提案: `IssueInvitationAction` (`@throws EmailAlreadyRegisteredException`, `@throws PendingInvitationAlreadyExistsException`)、`OnboardAction` (`@throws InvalidInvitationTokenException`)、`WithdrawAction` (`@throws SelfWithdrawForbiddenException`, `@throws UserAlreadyWithdrawnException`, `@throws HttpException`) などに `@throws` を追加する。**呼出側に対する契約を型から読めない部分は DocBlock で補完**するのが Pro 生レベルの規約 (rules/backend-types-and-docblocks.md:144-156)

**問題 3: 例外メッセージの一元管理**
- 全ドメイン例外がコンストラクタで日本語メッセージのデフォルト引数を持つが、`lang/ja/exceptions.php` 等の翻訳ファイルへの集約は未実施
- 改善提案: Basic スコープでは不要だが、Pro 生に「将来の多言語化準備」を意識させるため、`__('exceptions.invitation.already_accepted')` のような形式に寄せる選択肢を spec に書いておく

## 観点 6: その他(業界標準観点)

### 強み

**Eloquent `HasUlids` の徹底**:
- 全 12 Model で `use HasUlids` を採用、主キー文字列の URL 露出に対するセキュリティリスクを排除 (`User.php:19`, `Certification.php:18`, `Question.php:17`, ...)
- マイグレーションも `$table->ulid('id')->primary()` + `foreignUlid()` で一貫
- 評価: BookShelf 流の `id` 整数とは違う、業界トレンドに沿った選択

**Pint 整形 hook**:
- `.claude/settings.json` に PostToolUse hook が定義され、`vendor/bin/pint $F` がファイル編集後に自動実行
- ホスト側で `vendor/bin/pint` を直叩きしないよう Sail プレフィックスを `tech.md:48-69` で明示
- 評価: 教材 / 模範解答PJ / 提供PJ で一貫した整形を保証する仕組み

**Sail プレフィックス慣習の明文化**:
- `tech.md:48-69` で `sail artisan` / `sail composer` / `sail npm` を必須化
- BookShelf / ContactForm と同じ慣習を継続することで受講生の混乱を避けている

**PSR-4 オートロード**:
- `composer.json:30-35` で `App\\: app/`, `Database\\Factories\\: database/factories/`, `Database\\Seeders\\: database/seeders/` の標準構成
- カスタム namespace なし、Laravel デフォルトに寄せている

### 問題点

**問題 1: `declare(strict_types=1)` が完全未採用** ⚠️ 重大
- `app/` 配下の 208 PHP ファイル中、`declare(strict_types=1)` を持つファイルは **0 件**
- `backend-types-and-docblocks.md:198-220` で「全 PHP ファイル冒頭に必須」と明記、Pint 設定の `declare_strict_types: true` rule で自動付与される前提
- しかし `pint.json` が **存在しない** (`vendor/` 内のサードパーティ pint.json はあるが、模範解答PJのルートには無し)
- 結果として PostToolUse hook で Pint が走ってもデフォルト Laravel preset で整形され、`declare_strict_types` rule が走らない
- 教材的影響: 「Pro 生は型を厳密に扱う」という規約のキー要素が、模範解答PJ で実演されていない。受講生は提供PJ にも `declare(strict_types=1)` がないことで「不要なのか」と誤解する
- 改善提案:
  1. `模範解答プロジェクト/pint.json` を作成し、`backend-types-and-docblocks.md:362-376` の例の通り設定
  2. `提供プロジェクト/` にも同じ `pint.json` を配置
  3. 既存全 208 ファイルに `declare(strict_types=1)` を一括付与 (`sail bin pint` 1 回で実現)
  4. テスト全実行で何も壊れないことを確認 (`strict_types` は受側 (caller) 側のスコープルールなので、既存実装のキャスト挙動は変わらない可能性が高い)

**問題 2: `private readonly` 規約の不徹底** ⚠️ 重大
- `backend-types-and-docblocks.md:62-77` で「DI 依存は `readonly` で不変宣言(必須)」と明記
- 実装での `readonly` 採用件数 = 8 件 (例外プロパティ 5 件 + Action/Service 2 件 + MarkdownRenderingService 1 件)
- それ以外の主要 Action / Service の DI コンストラクタは `private` のみで `readonly` なし:
  - `app/UseCases/Auth/IssueInvitationAction.php:19-22` — `private UserStatusChangeService` + `private RevokeInvitationAction`
  - `app/UseCases/Auth/RevokeInvitationAction.php:15` — `private UserStatusChangeService`
  - `app/UseCases/Auth/OnboardAction.php:17` — `private UserStatusChangeService`
  - `app/UseCases/Auth/ExpireInvitationsAction.php:13` — `private UserStatusChangeService`
  - `app/UseCases/User/WithdrawAction.php:15` — `private UserStatusChangeService`
  - `app/UseCases/Certificate/IssueAction.php:17-20` — `private CertificateSerialNumberService` + `private CertificatePdfGenerator`
- 教材的影響: 規約と実装の乖離。受講生が模範解答を見て「`readonly` は別に要らないのか」と判断するリスク
- 改善提案: 全 Action / Service の DI コンストラクタを `private readonly` に修正 (10 件程度の Edit)。Pint には自動付与 rule がないので手動編集が必要だが、`sed` 系の機械置換でも対応可能

**問題 3: `@param array{...}` shape annotation の未採用**
- `backend-types-and-docblocks.md:101-130` で「`array $validated` のような型情報を持たない array には DocBlock で shape を必ず明示する」と必須レベル
- 実装での shape annotation 件数 = 1 件のみ (`OnboardAction.php:25` の `@param array{name: string, bio?: ?string, password: string} $validated`)
- `Certification\StoreAction`, `CertificationCategory\StoreAction`, `Question\StoreAction`, `User\UpdateAction` 等の全 store/update 系 Action で `array $validated` を受けるが shape annotation なし
- 教材的影響: 「array shape は DocBlock で書く」という Phpstan / 静的解析時代の標準が示されていない
- 改善提案:
  - 全 Action の `array $validated` パラメータに `@param array{key: type, ...}` を追加
  - 同期して FormRequest 側の `rules()` と shape を対応させる (例: `StoreRequest::rules()` の `'code' => required` ↔ `@param array{code: string, ...}`)
  - これは 20-30 件の Edit、機械的だが学習効果大

**問題 4: `@throws` 宣言の不足** (観点 5 で詳細)

**問題 5: 冗長 DocBlock**
- 一部の Service / Action で意味説明 DocBlock が薄い
- 例: `UseCases/CertificationCategory/StoreAction.php:9-17` — `__invoke()` の意味説明なし(が、メソッド名から自明なので問題なし)
- `UseCases/Auth/OnboardAction.php:21-25` のような「意味説明 DocBlock + shape annotation」の組み合わせを他の Action でも採用すべき (`@throws` 含む)

**問題 6: 環境変数命名の業界標準逸脱箇所**
- `.env.example:7` — `COMPOSE_PROJECT_NAME=certify-lms` は Sail/Docker Compose の慣習
- `.env.example:11-12` — `APP_PORT=8000` / `VITE_PORT=5174` の併走考慮
- 評価: Docker Compose の `COMPOSE_PROJECT_NAME` を `.env` に置く運用は実務寄りで問題なし

**問題 7: PSR-4 オートロード設定の追加余地**
- `composer.json:30-35` の標準 PSR-4 で問題ないが、将来 `App\Services\Gemini\` のようなネスト namespace が増えた場合、Composer の `optimize-autoloader` 運用方針も明示できると良い
- 現状の規模では問題なし

## 全体総評 + 優先改善アクション

### 総評

模範解答PJ の現実装(`auth` / `user-management` / `certification-management` / `content-management`)は、Laravel 業界標準を **「実務で通用するレベル」** で満たしている。Eloquent 慣習、FormRequest、Policy、Service Container DI、Schedule Command、`DB::afterCommit`、`lockForUpdate`、複合 index、テスト戦略のすべてが、ベテラン Laravel エンジニアが書く成果物に近い。Pro 生候補が「実務で詰まないため」の最後の関門として、十分なクオリティを持つ模範解答である。

特筆すべき強みは以下:
1. **規約(`.claude/rules/`)が実装と整合する設計** — Controller method 名 = Action クラス名、認可は Controller / FormRequest、データ整合性チェックは Action、というレイヤ責務分離が一貫
2. **状態遷移とドメイン例外の対応** — `ContentInvalidTransitionException` が `from` / `to` を持つ表現力、Symfony HTTP 例外との階層整合
3. **セキュリティ意識** — `forceFill` の限定運用、署名 URL、private/public ディスク分離、Markdown XSS 対策、Mass Assignment 保護
4. **テストカバレッジ** — Mail::fake / Storage::fake / Notification::fake の使い分け、認可漏れ / バリデーション失敗 / 状態遷移境界の網羅

一方で、`backend-types-and-docblocks.md` で「必須」レベルとした規約が **実装に反映されていない** という乖離が最大の課題。これは「規約を緩める」か「実装を底上げする」かの判断を要し、後続 14 Feature を実装する前に方針を確定すべき。受講生に見せる前に整合させなければ「規約は書いてあるが模範解答が守ってない」という致命的な教材矛盾が生じる。

### 優先改善アクション

**P0 (受講生公開前に必須修正)**

| # | 対象 | アクション | 影響範囲 |
|---|---|---|---|
| P0-1 | `pint.json` 不在 | 模範解答PJ / 提供PJ のルートに `pint.json` を作成、`declare_strict_types: true` rule を有効化 | 設定ファイル 2 件 |
| P0-2 | `declare(strict_types=1)` 全 0 件 | Pint 設定後に `sail bin pint` を実行して 208 ファイルに一括付与 | 208 ファイル(自動) |
| P0-3 | `private readonly` 規約不徹底 | 全 Action / Service の DI コンストラクタを `private readonly` に修正 | 約 10 ファイル(手動 sed) |
| P0-4 | `HasApiTokens` trait 残置 | `App\Models\User` から `HasApiTokens` を削除、`personal_access_tokens` migration も検討 | 1-2 ファイル |
| P0-5 | `tests/{Feature,Unit}/ExampleTest.php` 残置 | 削除 | 2 ファイル |

**P1 (Feature 実装フェーズ並走で対応)**

| # | 対象 | アクション | タイミング |
|---|---|---|---|
| P1-1 | `@param array{...}` shape annotation | 全 Action の `array $validated` に shape を付与 | 各 Feature 実装時に習慣化 |
| P1-2 | `@throws` 宣言 | 独自例外を投げる Action / Service に `@throws` を付与 | 各 Feature 実装時 |
| P1-3 | `WithdrawAction` の `HttpException` 汎用利用 | `InvitedUserWithdrawNotAllowedException` を独立クラス化 | user-management 改修 |
| P1-4 | `Certificate\IssueAction` の PDF 失敗時 Storage rollback | try-catch で Storage::delete を巻き戻す | certification-management 改修 |
| P1-5 | `Repository` パターンの初実装 | `ai-chat` / `mentoring` で `GeminiRepository` / `GoogleCalendarRepository` を Interface + Implementation で構築、`tests/Unit/Repositories/{Source}RepositoryTest.php` で `Http::fake()` 模範 | ai-chat / mentoring 実装時 |
| P1-6 | Resource パターンの初実装 | `analytics-export` で `app/Http/Resources/Api/Admin/` の命名規約を確立、後追いで `SectionImageController::store` も `SectionImageResource` に統一 | analytics-export 実装時 |

**P2 (将来拡張・余裕があれば)**

| # | 対象 | アクション |
|---|---|---|
| P2-1 | `ContentSearchPolicy` 新設 | `ContentSearchController::search` の認可を Policy で明示化 |
| P2-2 | `Observer` パターン導入 | `UserObserver` で `status` UPDATE を捕捉して `UserStatusLog` を確実に記録 |
| P2-3 | `Cache` 戦略の明示化 | `SidebarBadgeComposer` の高頻度クエリを 1 分キャッシュ |
| P2-4 | Schedule Command の chunk 化 | `ExpireInvitationsAction` を `lazy()` または `chunkById(500)` に書き換え、教材として示す |
| P2-5 | 翻訳ファイル準備 | 例外メッセージを `lang/ja/exceptions.php` に集約、多言語化の足場を見せる |
| P2-6 | スコープ Unit テスト追加 | `Question::scopePublished` 等 5 つのスコープを Unit テストでカバー |
| P2-7 | 同時実行テストの示唆 | `Certificate\IssueAction` の冪等性を fork ベースで検証する Advance テスト例 |

### 規約 vs 実装の乖離方針判断

受講生公開前に **必ず** 以下のどちらかを選択:

- **A. 規約に実装を合わせる(推奨)** — P0-1〜P0-5 を全実施。`backend-types-and-docblocks.md` の「必須」レベルを完全遵守する状態に底上げ。学習効果が最大化される。
- **B. 実装に規約を合わせる** — `backend-types-and-docblocks.md` を全面書き直し、`declare(strict_types=1)` / `private readonly` / `@param array{}` を「推奨」レベルに格下げ。学習効果は減るが工数最小。

`CLAUDE.md` の「修了条件 Pro 生 Junior Engineer 像」には「C1: FormRequest / Policy / Enum / SoftDeletes / `DB::transaction` 適切使用」と「C3: Pint・命名・コメント整理」が含まれており、Pint 整形で `declare(strict_types=1)` が自動付与される前提が成立しなければ C3 が形骸化する。よって **A 案を強く推奨** する。
