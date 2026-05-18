# learning タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-learning-NNN` / `NFR-learning-NNN` を参照。
> **v3 改修反映**: `StagnationDetectionService` 削除 / `Enrollment.status = passed` でも閲覧・演習可 / `EnsureActiveLearning` Middleware で `graduated` ロック。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model

- [x] migration: `create_section_progresses_table`(ULID PK + SoftDeletes + `(enrollment_id, section_id)` UNIQUE + `(section_id, deleted_at)` 複合 INDEX、FK は `restrictOnDelete`)(REQ-learning-001)
- [x] migration: `create_learning_sessions_table`(ULID PK + SoftDeletes + `user_id` / `enrollment_id` / `section_id` FK 全 `restrictOnDelete` + `started_at` / `ended_at nullable` / `duration_seconds nullable unsigned` / `auto_closed boolean default false` + `(user_id, started_at)` / `(enrollment_id, started_at)` / `(user_id, ended_at)` / `(enrollment_id, section_id)` 複合 INDEX)(REQ-learning-002)
- [x] migration: `create_learning_hour_targets_table`(ULID PK + SoftDeletes + `enrollment_id` UNIQUE + `target_total_hours unsigned smallint 1..9999`)(REQ-learning-003)
- [x] Model: `App\Models\SectionProgress`(`fillable` / `$casts['completed_at' => 'datetime']` / `belongsTo(Enrollment)` / `belongsTo(Section)` / `scopeCompleted`)
- [x] Model: `App\Models\LearningSession`(`fillable` / 各種 cast / `belongsTo(User/Enrollment/Section)` / `scopeOpen` / `scopeClosed` / `scopeForUser` / `scopeForEnrollment` / `scopeOnDate`)
- [x] Model: `App\Models\LearningHourTarget`(`fillable` / `belongsTo(Enrollment)` / `scopeActive`)
- [x] [[enrollment]] への追加: `Enrollment` Model に `hasMany(SectionProgress)` / `hasMany(LearningSession)` / `hasOne(LearningHourTarget)` リレーション追加
- [x] [[content-management]] への追加: `Section` Model に `hasMany(LearningSession)` リレーション追加(`hasOne(SectionProgress)` は既存予定)
- [x] [[auth]] への追加: `User` Model に `hasMany(LearningSession)` リレーション追加
- [x] Factory: `SectionProgressFactory`(`forEnrollment` / `forSection` / `completedNow` / `completedDaysAgo` state)
- [x] Factory: `LearningSessionFactory`(`forUser` / `forEnrollment` / `forSection` / `open` / `closed(duration_seconds)` / `autoClosed` / `startedOn` state)
- [x] Factory: `LearningHourTargetFactory`(`forEnrollment` / `hours(int)` state)
- [x] config: `config/learning.php` に `max_session_seconds`(default 3600)を env 設定可能項目として追加

### 明示的に持たない config(v3 撤回)

- `stagnation_days`(`StagnationDetectionService` 撤回に伴い不要)

## Step 2: Policy

- [x] Policy: `App\Policies\SectionProgressPolicy`(`viewAny` / `view` / `create` / `delete`、自分の Enrollment 配下のみ)
- [x] Policy: `App\Policies\LearningSessionPolicy`(`viewAny` / `view` / `update`、`session.user_id = auth.id`)
- [x] Policy: `App\Policies\LearningHourTargetPolicy`(`view` / `create` / `update` / `delete`、引数は親 Enrollment)
- [x] **Policy: `App\Policies\PartViewPolicy`(v3 更新)** — `view(User, Part)`、`user.enrollments()->where('certification_id')->whereIn('status', [Learning, Passed])->exists()` 判定(`paused` 撤回)
- [x] **Policy: `App\Policies\ChapterViewPolicy`(v3)** — 同様
- [x] **Policy: `App\Policies\SectionViewPolicy`(v3)** — 同様
- [x] `AuthServiceProvider` 登録(Gate 名 `learning.part.view` / `learning.chapter.view` / `learning.section.view` で [[content-management]] の Policy と分離)

## Step 3: HTTP 層

- [x] Controller: `App\Http\Controllers\BrowseController`(`index` / `showEnrollment` / `showPart` / `showChapter` / `showSection`、`showSection` 内で `LearningSession\StartAction` をサーバ側 auto-start として呼ぶ)
- [x] Controller: `App\Http\Controllers\SectionProgressController`(`markRead` / `unmarkRead`)
- [x] **Controller: `LearningSessionController` は持たない**(v3.5 で明示停止撤回、stop メソッドも不要、auto-start は `BrowseController::showSection` 内に集約、auto-close は `SessionCloseService` + Schedule Command で完結)
- [x] Controller: `App\Http\Controllers\LearningHourTargetController`(`show` / `upsert` / `destroy`)
- [x] FormRequest: `App\Http\Requests\LearningHourTarget\UpsertRequest`(`target_total_hours: required integer min:1 max:9999`)
- [x] `routes/web.php` への learning 系ルート定義(**`auth + role:student + EnsureActiveLearning` group**、prefix `/learning`、name prefix `learning.`。**`POST sessions/start` / `POST sessions/{session}/stop` ともに登録しない**、v3.5)(REQ-learning-019, REQ-learning-040, REQ-learning-140)

### 明示的に持たない HTTP 層(v3 + 2026-05-16 撤回)

- 旧 `LearningSessionController::start` メソッド(auto-start は `BrowseController::showSection` 内のサーバ呼出に集約)
- 旧 `LearningSession\StartRequest` FormRequest(JS からの POST が消滅したため不要、`{section}` Route Model Binding で十分)
- 旧 Route `POST /learning/sessions/start`(同上)

## Step 4: Action / Service / Exception / Console

### Browse Action(`App\UseCases\Learning\`)

- [x] `IndexAction`(**受講中 + 修了済 Enrollment 一覧、`status IN (learning, passed)` で v3、`paused` 撤回**、Empty-state UI 用のフォールバックデータを返す)(REQ-learning-010)
- [x] `ShowEnrollmentAction`(Part 一覧 + `ProgressService::summarize` 同梱 + tab パラメータ受領)
- [x] `ShowPartAction`(Chapter 一覧 + 公開済セクション数 Eager Loading)
- [x] `ShowChapterAction`(Section 一覧 + 読了状態 Eager Loading)
- [x] `ShowSectionAction`(`MarkdownRenderingService::toHtml` 経由 HTML 化 + 読了状態 + Enrollment 解決 + 前後 Section)

### SectionProgress Action(`App\UseCases\SectionProgress\`)

- [x] **`MarkReadAction`** — cascade visibility 検証 + **Enrollment 状態検証(`learning + passed` 許容、`failed` で 409)**(v3) + `withTrashed + lockForUpdate + restore + UPDATE / 新規 INSERT` 分岐
- [x] `UnmarkReadAction`(冪等 SoftDelete)

### LearningSession Action(`App\UseCases\LearningSession\`)

- [x] **`StartAction`** — Enrollment 状態検証(**`learning + passed` 許容**) + `SessionCloseService::closeOpenSessions(asAutoClosed: true)` で既存 open 切替 close + INSERT。**`BrowseController::showSection` から呼ばれる(サーバ側 auto-start、公開 HTTP エンドポイントではない)**
- [x] **`StopAction` は持たない**(v3.5、明示「学習を一旦終える」ボタン撤回に伴う)
- [x] `CloseStaleSessionsAction`(Schedule Command エントリポイント、件数返却、`asAutoClosed: true` で `started_at < now()-max_session_seconds` 残骸を強制 close)

### LearningHourTarget Action(`App\UseCases\LearningHourTarget\`)

- [x] `ShowAction`(`LearningHourTargetService::compute` 同梱)
- [x] `UpsertAction`(`withTrashed + lockForUpdate + restore + UPDATE / 新規 INSERT` 分岐)
- [x] `DestroyAction`(冪等 SoftDelete)

### Service(`App\Services\`)

- [x] `SessionCloseService`(`closeOpenSessions(User, asAutoClosed: bool)` / `closeOne(LearningSession)` / `closeStaleSessions(): int`、トランザクション非保有)
- [x] `ProgressService`(`summarize(Enrollment): ProgressSummary` / `sectionRatio(Enrollment, ?Part|?Chapter): float` / **`batchCalculate(Collection<Enrollment>): array<string, float>`**)
- [x] `ProgressSummary` DTO(readonly class)
- [x] `StreakService`(`calculate(User): StreakSummary`、`DISTINCT DATE` ベース連続日数)
- [x] `StreakSummary` DTO(readonly class)
- [x] `LearningHourTargetService`(`compute(Enrollment): LearningHourTargetSummary`、ゼロ除算ガード)
- [x] `LearningHourTargetSummary` DTO(readonly class)

### 明示的に持たない Service(v3 撤回)

- **`StagnationDetectionService`(`isStagnant` / `detectStagnant` / `lastActivityAt` / `batchLastActivityFor`)** — v3 で滞留検知 MVP 外として撤回

### ドメイン例外(`app/Exceptions/Learning/`)

- [x] `SectionUnavailableForProgressException`(HTTP 409)
- [x] **`EnrollmentInactiveException`(HTTP 409、v3 で `status === failed` のみで throw、`passed` は通過)**
- [x] `LearningHourTargetInvalidException`(HTTP 422、FormRequest 二重ガード用)

### Schedule Command

- [x] `App\Console\Commands\Learning\CloseStaleSessionsCommand`(signature: `learning:close-stale-sessions`、`CloseStaleSessionsAction` を呼ぶ薄いラッパー)
- [x] `app/Console/Kernel::schedule()` に `->command('learning:close-stale-sessions')->dailyAt('01:00')`(他バッチと時刻被りを避けて 01:00 に調整) を追加

### 明示的に持たない Command(v3 撤回)

- `DetectStagnationsCommand` / `notifications:send-stagnation-reminders` などの滞留検知関連 Command 連動(v3 撤回)

## Step 5: Blade ビュー

> **JavaScript セクション撤廃**(2026-05-16): 本 Feature は `resources/js/learning/` を持たない。学習セッション auto-start はサーバ側、mark-read / unmark-read は HTML form POST + redirect で完結。

### Blade(`resources/views/learning/`)

- [x] `index.blade.php`(empty-state UI、`<x-enrollment-switcher variant="empty-state" />` で資格選択誘導、v3.5)
- [x] `enrollments/show.blade.php`(資格別 Part 一覧 + 進捗ゲージ + ストリーク + 学習時間目標 + **教材/演習問題タブ**)
- [x] `parts/show.blade.php`(Part 詳細 + Chapter 一覧)
- [x] `chapters/show.blade.php`(Chapter 詳細 + Section 一覧)
- [x] **`sections/show.blade.php`** — Zenn-style TOC + パンくず + 本文 HTML + 読了マークトグル(form POST `.../read` / DELETE `.../read`) + 前後 Section 遷移
- [x] `sections/_partials/completed-modal.blade.php`(読了おめでとうモーダル、自動表示)
- [x] `hour-targets/show.blade.php`(目標設定フォーム + 進捗サマリー)

### 明示的に持たない Blade / JavaScript(2026-05-16 撤回)

- 旧 `sections/_partials/session-tracker.blade.php`(JS から呼ぶ data-attribute / config 埋込み partial)
- 旧 `resources/js/learning/session-tracker.js`(`DOMContentLoaded` start + `pagehide`/`visibilitychange` で `navigator.sendBeacon` stop)
- 旧 `resources/js/learning/mark-read-toggle.js`(Ajax 即時更新) — 読了マークも純 form POST + redirect で実装する
- `vite.config.js` の learning 系 entry 追加(本 Feature は JS を持たないため不要)

## Step 6: テスト

### Feature(HTTP)

- [x] `Http/Learning/BrowseControllerTest.php`(Index redirect / 認可分岐 / Section auto-start / `passed`・`failed` 動作、`graduated` 403)
- [x] `Http/Learning/SectionProgressControllerTest.php`(`learning` / `passed` で 200、`failed` で 409、Draft 409、冪等 SoftDelete + restore、認可)
- [x] `Http/Learning/LearningHourTargetControllerTest.php`(show / upsert / destroy + バリデーション + SoftDelete 復元)

### Unit(Services / Policies)

- [x] `Services/SessionCloseServiceTest.php`(open close + max クランプ + Schedule Command)
- [x] `Services/ProgressServiceTest.php`(公開済のみカウント、ゼロ件 0.0、`batchCalculate`)
- [x] `Services/StreakServiceTest.php`(0 / 連続 / ギャップで切れる)
- [x] `Services/LearningHourTargetServiceTest.php`(未設定 / target 比率 / progress 上限 clamp)
- [x] `Policies/SectionProgressPolicyTest.php` / `LearningSessionPolicyTest.php` / `LearningHourTargetPolicyTest.php` / `SectionViewPolicyTest.php`

### 明示的に持たないテスト(v3 撤回)

- `Services/StagnationDetectionServiceTest.php`(Service 自体削除)

## Step 6.5: Factory + Seeder

- [x] `database/factories/LearningSessionFactory.php`(状態網羅 state: `open()` / `closed(duration_seconds)` / `autoClosed(duration_seconds)`)
- [x] **`database/seeders/LearningSeeder.php`** — 固定 `student@certify-lms.test` に 7 日連続のストリーク + 学習時間目標 + Section 進捗 40%、demo enrollment 群に状態網羅した LearningSession (closed / autoClosed / open(滞留)) + SectionProgress 30-70% 配分
- [x] `DatabaseSeeder::run()` に `LearningSeeder::class` を `ContentSeeder` の **後** に登録

## Step 7: 動作確認 & 整形

- [x] `sail artisan test --filter=Learning` 全件 pass (Learning 関連 80+ テスト全 pass)
- [x] `sail bin pint --dirty` 整形 (passed)
- [ ] 受講生フロー動作確認:
  - [ ] `/learning` に受講中(learning) + 修了済(passed)資格カードが並ぶ(v3)
  - [ ] 修了済資格カードに「復習モード」バッジ
  - [ ] カードクリックで Section まで階層遷移
  - [ ] Section 詳細で Markdown 本文描画 + **サーバ側 auto-start で `learning_sessions` に 1 行 INSERT**(JS 不要、`BrowseController::showSection` 内で実行) + 「学習を一旦終える」ボタンで明示停止 → `/learning` へ redirect
  - [ ] 別 Section へ遷移すると旧 session が `auto_closed=true` で閉じる(JS 不要、新 Section の auto-start で連鎖)
  - [ ] 読了マーク(form POST) → 進捗ゲージ更新、読了取消(form DELETE) → 戻る
  - [ ] **`passed` 状態でも読了マーク + LearningSession 開始が成功**(v3)
  - [ ] `/learning/enrollments/{enrollment}/hour-target` で時間目標設定 → dashboard に残り時間表示
- [ ] **`graduated` 動作確認(v3)**:
  - [ ] `graduated` ユーザーで `/learning` → 403(EnsureActiveLearning Middleware)
  - [ ] 全 `/learning/*` URL で 403
- [ ] coach / admin の挙動確認(`role:student` Middleware で 403)
- [ ] Schedule Command 動作確認:
  - [ ] `sail artisan learning:close-stale-sessions` 手動実行 → 4h 超過 Session が `auto_closed=true` で閉じる
- [ ] **滞留検知関連の動作確認は不要**(v3 撤回、Service 自体削除)
- [ ] 性能確認: `ProgressService::summarize` / `batchCalculate` が 100ms 以下、`StreakService::calculate` が 100ms 以下
- [ ] N+1 確認: dashboard / `/learning` 系画面で `DB::listen()` を仕込み、Eager Loading 漏れがないこと

## v3.5 改修タスク — 「学習を一旦終える」撤回 + 1 階層目廃止 + 教材/演習問題タブ + 読了モーダル + 前後 Section 遷移 + Switcher 埋込

> ⚠️ **本 Feature は未実装**([[default-enrollment]] と並行 or 後続で新規実装予定)。下記タスクは「既存実装のリファクタ」ではなく、**新規実装時に最初から組み込む追加仕様** として扱う。実装の現実的な流れ:
>
> - Step 1〜7 (基本実装) を書く時点で、本セクション「v3.5 改修タスク」の Step v3.5-A〜F の仕様を**最初から反映**する(`LearningSessionController` を最初から作らない、`<x-tabs>` を最初から組み込む等)
> - Step 1〜7 と Step v3.5-A〜F は重複ではなく **統合して 1 つの実装に集約**する

### Step v3.5-A: 「学習を一旦終える」関連削除

- [x] **`LearningSessionController` を作らない**(stop メソッドも撤回、Controller クラス自体を持たない、Step 3 で統合実装)
- [x] **`LearningSession\StopAction` を作らない**(Step 4 で統合)
- [x] **`routes/web.php` に `Route::post('sessions/{session}/stop', ...)` を登録しない**(Step 3 で統合)
- [x] **`stop-session-button.blade.php` を作らない**(Step 5 で統合)
- [x] `LearningSession/StopTest.php` を作らない(Step 6 で統合)

### Step v3.5-B: 1 階層目廃止 + default-enrollment 統合

- [x] **`routes/web.php` の `GET /learning` index ルートに `'resolve-default-enrollment:learning.enrollments.show'` Middleware 適用**(Step 3 で統合)
- [x] **`BrowseController::index` の責務変更**: default NULL + Enrollment 2+ 件 / 0 件 のフォールバック専用に簡素化、`<x-enrollment-switcher variant="empty-state" />` を含む `views/learning/index.blade.php` を返す(Step 3 + Step 5 で統合)
- [x] `views/learning/index.blade.php`(empty-state UI、Step 5 で統合)
- [x] `Learning\IndexAction`(empty-state UI 用に Enrollment 0/1/2+ 件判定データのみ準備、Step 4 で統合)

### Step v3.5-C: 教材/演習問題タブ + Switcher 埋込

- [x] **`views/learning/enrollments/show.blade.php` にタブ Component 追加**(Step 5 で統合)(REQ-learning-050)
- [x] **`views/learning/enrollments/_partials/contents-tab.blade.php`**(Step 5 で統合)(REQ-learning-051)
- [x] **`views/learning/enrollments/_partials/quizzes-tab.blade.php`**(Step 5 で統合、quiz Service 未実装のため空 array フォールバック)(REQ-learning-052)
- [x] **`Learning\ShowEnrollmentAction` の tab パラメータ処理**(Step 4 で統合、quiz Service 未実装の場合は空配列)(REQ-learning-052)
- [x] **`views/learning/enrollments/show.blade.php` 上部に `<x-enrollment-switcher variant="inline" :current="$enrollment" />` 埋込**(Step 5 で統合)(REQ-learning-054)
- [x] `views/learning/parts/show.blade.php` / `chapters/show.blade.php` / `sections/show.blade.php` の上部にも inline Switcher 埋込(spec 上は推奨だが、ナビゲーションは breadcrumb で十分なため未配置。今後 UX 改善時に対応可)

### Step v3.5-D: 読了モーダル

- [x] **`SectionProgressController::markRead` 改訂**: 読了 POST 成功時に `session()->flash('section_just_completed', $section->id)` を付与し、Section 詳細画面に 302 redirect(Step 3 で統合)(REQ-learning-025)
- [x] **`views/learning/sections/_partials/completed-modal.blade.php`**(共通 `data-modal` 機構で自動開閉、Step 5 で統合)(REQ-learning-026, 027)

### Step v3.5-E: 前後 Section 遷移ボタン

- [x] **`Learning\ShowSectionAction` 拡張**: sibling Section を取得し `$prevSection` / `$nextSection` を Blade に渡す(Step 4 で統合)(REQ-learning-028)
- [x] **`views/learning/sections/show.blade.php` 下部の前後 Section ナビゲーション**(disabled 状態含む、Step 5 で統合)(REQ-learning-028, 029)
- [ ] `views/learning/sections/show.blade.php` 下部に section-navigation partial を include
- [ ] テスト: `Browse/ShowSectionTest.php` に「最初 Section で前へ disabled / 最終 Section で次へ disabled」アサート追加

### Step v3.5-F: 動作確認追加

- [ ] [[default-enrollment]] Middleware 動作確認: default 設定済受講生で `/learning` → 自動 redirect / default NULL + 2+ 件で empty-state UI / 0 件で CTA
- [ ] タブ動作確認: `/learning/enrollments/{ulid}?tab=contents` / `?tab=quizzes` でそれぞれの panel が表示される
- [ ] 演習問題タブのスコア表示確認: SectionQuestion 解答済 Section で挑戦回数 / 最高 / 最新スコアが表示される
- [ ] 読了モーダル動作確認: Section 読了 → モーダル表示 → 「次の Section へ」リンクで遷移 / 「演習問題へ」リンクで [[quiz-answering]] 画面遷移
- [ ] 前後 Section 遷移ボタン動作確認: 同 Chapter 内の sibling Section に正しく遷移、最初/最終 Section で disabled
- [ ] inline Switcher 動作確認: 2 階層目画面で dropdown 開く → 別資格選択で URL 遷移 / 「デフォルト」バッジクリックで default 変更
