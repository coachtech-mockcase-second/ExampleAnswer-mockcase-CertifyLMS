# learning タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-learning-NNN` / `NFR-learning-NNN` を参照。
> **v3 改修反映**: `StagnationDetectionService` 削除 / `Enrollment.status = passed` でも閲覧・演習可 / `EnsureActiveLearning` Middleware で `graduated` ロック。
> コマンドはすべて `sail` プレフィックス。

## Step 1: Migration & Model

- [ ] migration: `create_section_progresses_table`(ULID PK + SoftDeletes + `(enrollment_id, section_id)` UNIQUE + `(section_id, deleted_at)` 複合 INDEX、FK は `restrictOnDelete`)(REQ-learning-001)
- [ ] migration: `create_learning_sessions_table`(ULID PK + SoftDeletes + `user_id` / `enrollment_id` / `section_id` FK 全 `restrictOnDelete` + `started_at` / `ended_at nullable` / `duration_seconds nullable unsigned` / `auto_closed boolean default false` + `(user_id, started_at)` / `(enrollment_id, started_at)` / `(user_id, ended_at)` / `(enrollment_id, section_id)` 複合 INDEX)(REQ-learning-002)
- [ ] migration: `create_learning_hour_targets_table`(ULID PK + SoftDeletes + `enrollment_id` UNIQUE + `target_total_hours unsigned smallint 1..9999`)(REQ-learning-003)
- [ ] Model: `App\Models\SectionProgress`(`fillable` / `$casts['completed_at' => 'datetime']` / `belongsTo(Enrollment)` / `belongsTo(Section)` / `scopeCompleted`)
- [ ] Model: `App\Models\LearningSession`(`fillable` / 各種 cast / `belongsTo(User/Enrollment/Section)` / `scopeOpen` / `scopeClosed` / `scopeForUser` / `scopeForEnrollment` / `scopeOnDate`)
- [ ] Model: `App\Models\LearningHourTarget`(`fillable` / `belongsTo(Enrollment)` / `scopeActive`)
- [ ] [[enrollment]] への追加: `Enrollment` Model に `hasMany(SectionProgress)` / `hasMany(LearningSession)` / `hasOne(LearningHourTarget)` リレーション追加
- [ ] [[content-management]] への追加: `Section` Model に `hasMany(LearningSession)` リレーション追加(`hasOne(SectionProgress)` は既存予定)
- [ ] [[auth]] への追加: `User` Model に `hasMany(LearningSession)` リレーション追加
- [ ] Factory: `SectionProgressFactory`(`forEnrollment` / `forSection` / `completedNow` / `completedDaysAgo` state)
- [ ] Factory: `LearningSessionFactory`(`forUser` / `forEnrollment` / `forSection` / `open` / `closed(duration_seconds)` / `autoClosed` / `startedOn` state)
- [ ] Factory: `LearningHourTargetFactory`(`forEnrollment` / `hours(int)` state)
- [ ] config: `config/app.php` に `learning_max_session_seconds`(default 14400)を env 設定可能項目として追加

### 明示的に持たない config(v3 撤回)

- `stagnation_days`(`StagnationDetectionService` 撤回に伴い不要)

## Step 2: Policy

- [ ] Policy: `App\Policies\SectionProgressPolicy`(`viewAny` / `view` / `create` / `delete`、自分の Enrollment 配下のみ)
- [ ] Policy: `App\Policies\LearningSessionPolicy`(`viewAny` / `view` / `update`、`session.user_id = auth.id`)
- [ ] Policy: `App\Policies\LearningHourTargetPolicy`(`view` / `create` / `update` / `delete`、引数は親 Enrollment)
- [ ] **Policy: `App\Policies\PartViewPolicy`(v3 更新)** — `view(User, Part)`、`user.enrollments()->where('certification_id')->whereIn('status', [Learning, Passed])->exists()` 判定(`paused` 撤回)
- [ ] **Policy: `App\Policies\ChapterViewPolicy`(v3)** — 同様
- [ ] **Policy: `App\Policies\SectionViewPolicy`(v3)** — 同様
- [ ] `AuthServiceProvider` 登録(Gate 名 `learning.part.view` / `learning.chapter.view` / `learning.section.view` で [[content-management]] の Policy と分離)

## Step 3: HTTP 層

- [ ] Controller: `App\Http\Controllers\BrowseController`(`index` / `showEnrollment` / `showPart` / `showChapter` / `showSection`、`showSection` 内で `LearningSession\StartAction` をサーバ側 auto-start として呼ぶ)
- [ ] Controller: `App\Http\Controllers\SectionProgressController`(`markRead` / `unmarkRead`)
- [ ] **Controller: `App\Http\Controllers\LearningSessionController`(`stop` のみ)** — `start` メソッドは持たない(auto-start は `BrowseController::showSection` 内に集約、JS / 公開エンドポイントとしては存在しない)
- [ ] Controller: `App\Http\Controllers\LearningHourTargetController`(`show` / `upsert` / `destroy`)
- [ ] FormRequest: `App\Http\Requests\LearningHourTarget\UpsertRequest`(`target_total_hours: required integer min:1 max:9999`)
- [ ] `routes/web.php` への learning 系ルート定義(**`auth + role:student + EnsureActiveLearning` group**、prefix `/learning`、name prefix `learning.`。**`POST sessions/start` は登録しない**、`POST sessions/{session}/stop` のみ登録)(REQ-learning-019, REQ-learning-040, REQ-learning-140)

### 明示的に持たない HTTP 層(v3 + 2026-05-16 撤回)

- 旧 `LearningSessionController::start` メソッド(auto-start は `BrowseController::showSection` 内のサーバ呼出に集約)
- 旧 `LearningSession\StartRequest` FormRequest(JS からの POST が消滅したため不要、`{section}` Route Model Binding で十分)
- 旧 Route `POST /learning/sessions/start`(同上)

## Step 4: Action / Service / Exception / Console

### Browse Action(`App\UseCases\Learning\`)

- [ ] `IndexAction`(**受講中 + 修了済 Enrollment 一覧、`status IN (learning, passed)` で v3、`paused` 撤回**、`withMax('learningSessions', 'started_at')` Eager Loading)(REQ-learning-010)
- [ ] `ShowEnrollmentAction`(Part 一覧 + `ProgressService::summarize` 同梱)
- [ ] `ShowPartAction`(Chapter 一覧 + 公開済セクション数 Eager Loading)
- [ ] `ShowChapterAction`(Section 一覧 + 読了状態 Eager Loading)
- [ ] `ShowSectionAction`(`MarkdownRenderingService::toHtml` 経由 HTML 化 + 読了状態 + Enrollment 解決)

### SectionProgress Action(`App\UseCases\SectionProgress\`)

- [ ] **`MarkReadAction`** — cascade visibility 検証 + **Enrollment 状態検証(`learning + passed` 許容、`failed` で 409)**(v3) + `withTrashed + lockForUpdate + restore + UPDATE / 新規 INSERT` 分岐
- [ ] `UnmarkReadAction`(冪等 SoftDelete)

### LearningSession Action(`App\UseCases\LearningSession\`)

- [ ] **`StartAction`** — Enrollment 状態検証(**`learning + passed` 許容**) + `SessionCloseService::closeOpenSessions(asAutoClosed: true)` で既存 open 切替 close + INSERT。**`BrowseController::showSection` から呼ばれる(サーバ側 auto-start、公開 HTTP エンドポイントではない)**
- [ ] **`StopAction`** — `LearningSessionController::stop` から呼ばれる(明示「学習を一旦終える」ボタン経由)。冪等返却 + `SessionCloseService::closeOne(asAutoClosed: false)` で `ended_at + duration_seconds clamp` UPDATE
- [ ] `CloseStaleSessionsAction`(Schedule Command エントリポイント、件数返却、`asAutoClosed: true` で `started_at < now()-max_session_seconds` 残骸を強制 close)

### LearningHourTarget Action(`App\UseCases\LearningHourTarget\`)

- [ ] `ShowAction`(`LearningHourTargetService::compute` 同梱)
- [ ] `UpsertAction`(`withTrashed + lockForUpdate + restore + UPDATE / 新規 INSERT` 分岐)
- [ ] `DestroyAction`(冪等 SoftDelete)

### Service(`App\Services\`)

- [ ] `SessionCloseService`(`closeOpenSessions(User, asAutoClosed: bool)` / `closeOne(LearningSession)` / `closeStaleSessions(): int`、トランザクション非保有)
- [ ] `ProgressService`(`summarize(Enrollment): ProgressSummary` / `sectionRatio(Enrollment, ?Part|?Chapter): float` / **`batchCalculate(Collection<Enrollment>): array<string, float>`**([[analytics-export]] / [[dashboard]] 用、1 ショット SQL))(REQ-learning-060)
- [ ] `ProgressSummary` DTO(readonly class)
- [ ] `StreakService`(`calculate(User): StreakSummary`、`DISTINCT DATE + CONVERT_TZ` クエリ)
- [ ] `StreakSummary` DTO(readonly class)
- [ ] `LearningHourTargetService`(`compute(Enrollment): LearningHourTargetSummary`、ゼロ除算ガード)
- [ ] `LearningHourTargetSummary` DTO(readonly class)

### 明示的に持たない Service(v3 撤回)

- **`StagnationDetectionService`(`isStagnant` / `detectStagnant` / `lastActivityAt` / `batchLastActivityFor`)** — v3 で滞留検知 MVP 外として撤回

### ドメイン例外(`app/Exceptions/Learning/`)

- [ ] `SectionUnavailableForProgressException`(HTTP 409)
- [ ] **`EnrollmentInactiveException`(HTTP 409、v3 で `status === failed` のみで throw、`passed` は通過)**
- [ ] `LearningHourTargetInvalidException`(HTTP 422、FormRequest 二重ガード用)

### Schedule Command

- [ ] `App\Console\Commands\Learning\CloseStaleSessionsCommand`(signature: `learning:close-stale-sessions`、`CloseStaleSessionsAction` を呼ぶ薄いラッパー)
- [ ] `app/Console/Kernel::schedule()` に `->command('learning:close-stale-sessions')->dailyAt('00:30')` を追加

### 明示的に持たない Command(v3 撤回)

- `DetectStagnationsCommand` / `notifications:send-stagnation-reminders` などの滞留検知関連 Command 連動(v3 撤回)

## Step 5: Blade ビュー

> **JavaScript セクション撤廃**(2026-05-16): 本 Feature は `resources/js/learning/` を持たない。学習セッション auto-start はサーバ側、stop / mark-read / unmark-read は HTML form POST + redirect で完結。

### Blade(`resources/views/learning/`)

- [ ] `index.blade.php`(**受講中 + 修了済 Enrollment カードグリッド**(v3、`passed` も含む)、各カードに資格名 / 現在ターム / 直近活動日 / 進捗 / 試験日カウントダウン / **修了済バッジ + 復習モードリンク**(v3))
- [ ] `enrollments/show.blade.php`(資格別 Part 一覧 + 進捗ゲージ + ストリーク + 学習時間目標)
- [ ] `parts/show.blade.php`(Part 詳細 + Chapter 一覧 + Chapter 別進捗バー)
- [ ] `chapters/show.blade.php`(Chapter 詳細 + Section 一覧 + 読了バッジ + Quiz 遷移リンク)
- [ ] **`sections/show.blade.php`** — パンくず + 本文 HTML + 読了マークトグル(form POST `.../read` / DELETE `.../read`) + Quiz 遷移 + **「学習を一旦終える」ボタン**(form POST `/learning/sessions/{currentSession}/stop`、`@csrf`、サーバ側 auto-start で取得した `$currentSession` を Blade に渡して hidden 値とする)
- [ ] `sections/_partials/markdown-body.blade.php` / `read-toggle.blade.php` / `stop-session-button.blade.php`(明示停止ボタンのみ、旧 `session-tracker.blade.php` は撤回)
- [ ] `hour-targets/_partials/form.blade.php` / `summary-card.blade.php`
- [ ] `_partials/breadcrumb.blade.php`

### 明示的に持たない Blade / JavaScript(2026-05-16 撤回)

- 旧 `sections/_partials/session-tracker.blade.php`(JS から呼ぶ data-attribute / config 埋込み partial)
- 旧 `resources/js/learning/session-tracker.js`(`DOMContentLoaded` start + `pagehide`/`visibilitychange` で `navigator.sendBeacon` stop)
- 旧 `resources/js/learning/mark-read-toggle.js`(Ajax 即時更新) — 読了マークも純 form POST + redirect で実装する
- `vite.config.js` の learning 系 entry 追加(本 Feature は JS を持たないため不要)

## Step 6: テスト

### Feature(HTTP)

- [ ] `Browse/IndexTest.php`(**`learning` + `passed` 両方の Enrollment 表示**(v3)、`failed` 除外、他者 Enrollment 不表示、coach/admin 403、**`graduated` ユーザー 403**(EnsureActiveLearning))
- [ ] `Browse/ShowEnrollmentTest.php`(自分の Enrollment OK、他者 403、SoftDelete 済 404)
- [ ] `Browse/ShowPartTest.php`(**`learning + passed` で OK**、未登録資格 403、非公開 404)
- [ ] `Browse/ShowChapterTest.php` / `ShowSectionTest.php`(同様、Markdown HTML 化確認)
- [ ] `SectionProgress/MarkReadTest.php`(**`passed` で 200**(v3、復習として読了マーク可)、`learning` で 200、`failed` で 409、Draft Section 409、未登録資格 403)
- [ ] `SectionProgress/UnmarkReadTest.php`(冪等)
- [ ] **`Browse/ShowSectionTest.php` 追加観点**: Section 詳細表示時にサーバ側 auto-start で `learning_sessions` に 1 行 INSERT される / 既存 open session があれば `auto_closed=true` で先に閉じる / `failed` Enrollment で `EnrollmentInactiveException` 409 / `learning` `passed` で 200(v3)
- [ ] `LearningSession/StopTest.php`(明示「学習を一旦終える」ボタン POST、冪等、`max_session_seconds` クランプ、1 秒下限、CSRF 必須、本人検証、redirect to `/learning`)
- [ ] **明示的に持たないテスト**: 旧 `LearningSession/StartTest.php`(JS からの `POST /learning/sessions/start` を検証していたが、auto-start に集約されたため `Browse/ShowSectionTest.php` 内でカバー)
- [ ] `LearningHourTarget/{Show,Upsert,Destroy}Test.php`
- [ ] **`EnsureActiveLearningTest.php`(v3)** — `graduated` で全エンドポイント 403、`in_progress` で 200

### Feature(UseCases)

- [ ] `Learning/ShowEnrollmentActionTest.php`(Part 一覧 + ProgressService 同梱の Eager Loading)
- [ ] **`SectionProgress/MarkReadActionTest.php`** — restore + UPDATE 分岐、Draft 連鎖時の例外、Enrollment 状態別の例外(`passed` 通過、`failed` で例外)
- [ ] `LearningSession/{Start,Stop,CloseStaleSessions}ActionTest.php`(状態遷移境界、`max_session_seconds` クランプ)
- [ ] `LearningHourTarget/UpsertActionTest.php`(SoftDeleted 復元)

### Unit(Services / Policies)

- [ ] `Services/SessionCloseServiceTest.php`(状態遷移 + max クランプ + 1 秒下限)
- [ ] `Services/ProgressServiceTest.php`(公開済のみカウント、Draft / SoftDelete 除外、ゼロ件 0.0、複数資格混在絞込、**`batchCalculate` の 1 クエリ集計**(v3 で analytics-export 用))
- [ ] `Services/StreakServiceTest.php`(0 / 1 / 連続 / 間飛び / longest_streak / TZ 考慮)
- [ ] `Services/LearningHourTargetServiceTest.php`(target 未設定 / studied > target / `exam_date` 超過 / `progress_ratio` 上限 / ゼロ除算)
- [ ] `Policies/{SectionProgress,LearningSession,LearningHourTarget}PolicyTest.php`
- [ ] **`Policies/{Part,Chapter,Section}ViewPolicyTest.php`(v3)** — `status IN (learning, passed)` 判定、`paused` テスト削除

### 明示的に持たないテスト(v3 撤回)

- `Services/StagnationDetectionServiceTest.php`(Service 自体削除)

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Learning` 全件 pass
- [ ] `sail bin pint --dirty` 整形
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
