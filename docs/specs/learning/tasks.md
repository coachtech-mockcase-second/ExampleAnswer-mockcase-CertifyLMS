# learning タスクリスト

> 1 タスク = 1 コミット粒度。Step 単位で順次実装し、完了したものから `[x]` に更新する。
> 関連要件 ID は `requirements.md` の `REQ-learning-NNN` / `NFR-learning-NNN` を参照。
> コマンドはすべて `sail` プレフィックス（`tech.md` の「コマンド慣習」参照）。

## Step 1: Migration & Model

- [ ] migration: `create_section_progresses_table`（ULID PK + SoftDeletes + `(enrollment_id, section_id)` UNIQUE + `(section_id, deleted_at)` 複合 INDEX + `deleted_at` INDEX、FK は `restrictOnDelete`）（REQ-learning-001, REQ-learning-004, REQ-learning-005, NFR-learning-003）
- [ ] migration: `create_learning_sessions_table`（ULID PK + SoftDeletes + `user_id` / `enrollment_id` / `section_id` FK 全 `restrictOnDelete` + `started_at` / `ended_at nullable` / `duration_seconds nullable unsigned` / `auto_closed boolean default false` + `(user_id, started_at)` / `(enrollment_id, started_at)` / `(user_id, ended_at)` / `(enrollment_id, section_id)` 複合 INDEX + `deleted_at` INDEX）（REQ-learning-002, REQ-learning-004, REQ-learning-005, REQ-learning-006, NFR-learning-003）
- [ ] migration: `create_learning_hour_targets_table`（ULID PK + SoftDeletes + `enrollment_id` UNIQUE + `target_total_hours unsigned smallint` + `deleted_at` INDEX、FK は `restrictOnDelete`）（REQ-learning-003, REQ-learning-004, NFR-learning-003）
- [ ] Model: `App\Models\SectionProgress`（`fillable` / `$casts['completed_at' => 'datetime']` / `belongsTo(Enrollment::class)` / `belongsTo(Section::class)` / `scopeCompleted()`）（REQ-learning-001, REQ-learning-007）
- [ ] Model: `App\Models\LearningSession`（`fillable` / `$casts['started_at' => 'datetime', 'ended_at' => 'datetime', 'duration_seconds' => 'integer', 'auto_closed' => 'boolean']` / `belongsTo(User::class)` / `belongsTo(Enrollment::class)` / `belongsTo(Section::class)` / `scopeOpen()` / `scopeClosed()` / `scopeForUser()` / `scopeForEnrollment()` / `scopeOnDate()`）（REQ-learning-002, REQ-learning-007）
- [ ] Model: `App\Models\LearningHourTarget`（`fillable` / `belongsTo(Enrollment::class)` / `scopeActive()`）（REQ-learning-003）
- [ ] [[enrollment]] への追加: `Enrollment` モデルに `hasMany(SectionProgress::class)` / `hasMany(LearningSession::class)` / `hasOne(LearningHourTarget::class)` リレーション追加（[[enrollment]] design.md 既定の `goals` / `notes` / `statusLogs` と並列）
- [ ] [[content-management]] への追加: `Section` モデルに `hasOne(SectionProgress::class)` リレーション宣言が既定なので確認のみ。`hasMany(LearningSession::class)` を追加
- [ ] [[auth]] への追加: `User` モデルに `hasMany(LearningSession::class)` リレーション追加
- [ ] Factory: `SectionProgressFactory`（`forEnrollment($enrollment)` / `forSection($section)` / `completedNow()` / `completedDaysAgo(int $days)` state）
- [ ] Factory: `LearningSessionFactory`（`forUser($user)` / `forEnrollment($enrollment)` / `forSection($section)` / `open()` / `closed(int $durationSeconds)` / `autoClosed()` / `startedOn(Carbon $date)` state）
- [ ] Factory: `LearningHourTargetFactory`（`forEnrollment($enrollment)` / `hours(int $h)` state）
- [ ] config: `config/app.php` に `learning_max_session_seconds`（default 14400）と `stagnation_days`（default 7）の env 設定可能項目を追加（REQ-learning-042, REQ-learning-121）

## Step 2: Policy

- [ ] Policy: `App\Policies\SectionProgressPolicy`（`viewAny` / `view` / `create` / `delete`）（REQ-learning-141）
- [ ] Policy: `App\Policies\LearningSessionPolicy`（`viewAny` / `view` / `update`）（REQ-learning-142）
- [ ] Policy: `App\Policies\LearningHourTargetPolicy`（`view` / `create` / `update` / `delete`、引数は親 Enrollment）（REQ-learning-097, REQ-learning-143）
- [ ] Policy: `App\Policies\PartViewPolicy`（`view`、受講登録 + active 判定）（REQ-learning-017, REQ-learning-144）
- [ ] Policy: `App\Policies\ChapterViewPolicy`（同上、親が Chapter）（REQ-learning-017, REQ-learning-144）
- [ ] Policy: `App\Policies\SectionViewPolicy`（同上、親が Section）（REQ-learning-017, REQ-learning-144）
- [ ] `AuthServiceProvider` で `LearningHourTargetPolicy` を `Gate::define('learning-hour-target.*', ...)` の Gate ベースで登録、または `policy(Enrollment::class, LearningHourTargetPolicy::class)` の二重登録方式で対応（既存 EnrollmentPolicy との競合を避ける場合は Gate ベース推奨）
- [ ] `PartViewPolicy` / `ChapterViewPolicy` / `SectionViewPolicy` の `AuthServiceProvider` 登録（`Gate::policy(Part::class, PartViewPolicy::class)` は既存 [[content-management]] の `PartPolicy` と競合するので、本 Feature では Gate 名 `learning.part.view` / `learning.chapter.view` / `learning.section.view` で登録）

## Step 3: HTTP 層

- [ ] Controller: `App\Http\Controllers\BrowseController`（`index` / `showEnrollment` / `showPart` / `showChapter` / `showSection`、Controller method 名と Action 名一致）（REQ-learning-010, REQ-learning-011, REQ-learning-012, REQ-learning-013, REQ-learning-014）
- [ ] Controller: `App\Http\Controllers\SectionProgressController`（`markRead` / `unmarkRead`、Controller method 名 = Action 名）（REQ-learning-020, REQ-learning-022）
- [ ] Controller: `App\Http\Controllers\LearningSessionController`（`start` / `stop`）（REQ-learning-040, REQ-learning-043）
- [ ] Controller: `App\Http\Controllers\LearningHourTargetController`（`show` / `upsert` / `destroy`）（REQ-learning-090, REQ-learning-091, REQ-learning-092）
- [ ] FormRequest: `App\Http\Requests\LearningSession\StartRequest`（`section_id: required ulid exists:sections,id`、`authorize` で student ロール確認）（REQ-learning-040）
- [ ] FormRequest: `App\Http\Requests\LearningHourTarget\UpsertRequest`（`target_total_hours: required integer min:1 max:9999`、`authorize` で Gate `learning-hour-target.update` 経由 Policy 呼出）（REQ-learning-093, REQ-learning-097）
- [ ] `routes/web.php` への learning 系ルート定義（`auth + role:student` group、prefix `/learning`、name prefix `learning.`、Browse / SectionProgress / LearningSession / LearningHourTarget 全エンドポイント）（REQ-learning-140, REQ-learning-145）

## Step 4: Action / Service / Exception / Console

### Browse Action（`App\UseCases\Learning\`）
- [ ] `IndexAction`（受講中 + 休止 Enrollment 一覧、`withMax('learningSessions', 'started_at')` Eager Loading）（REQ-learning-010, NFR-learning-006）
- [ ] `ShowEnrollmentAction`（Part 一覧 + `ProgressService::summarize` 同梱）（REQ-learning-011, REQ-learning-067, NFR-learning-006）
- [ ] `ShowPartAction`（Chapter 一覧 + `published_sections_count` Eager Loading）（REQ-learning-012, NFR-learning-006）
- [ ] `ShowChapterAction`（Section 一覧 + 読了状態 Eager Loading）（REQ-learning-013, NFR-learning-006）
- [ ] `ShowSectionAction`（`MarkdownRenderingService::toHtml` 経由 HTML 化 + 読了状態 + Enrollment 解決）（REQ-learning-014, REQ-learning-018）

### SectionProgress Action（`App\UseCases\SectionProgress\`）
- [ ] `MarkReadAction`（cascade visibility 検証 + Enrollment 状態検証 + `withTrashed + lockForUpdate + restore + UPDATE / 新規 INSERT` 分岐、**進捗 Service 呼出は行わない**）（REQ-learning-020, REQ-learning-021, REQ-learning-023, REQ-learning-024, REQ-learning-025, NFR-learning-001）
- [ ] `UnmarkReadAction`（冪等 SoftDelete）（REQ-learning-022, NFR-learning-001）

### LearningSession Action（`App\UseCases\LearningSession\`）
- [ ] `StartAction`（Enrollment 状態検証 + `SessionCloseService::closeOpenSessions` + INSERT）（REQ-learning-040, REQ-learning-041, REQ-learning-046, REQ-learning-048, NFR-learning-001）
- [ ] `StopAction`（冪等返却 + `SessionCloseService::closeOne` 呼出）（REQ-learning-043, REQ-learning-044, REQ-learning-049, NFR-learning-001）
- [ ] `CloseStaleSessionsAction`（Schedule Command エントリポイント、件数返却）（REQ-learning-047, NFR-learning-001）

### LearningHourTarget Action（`App\UseCases\LearningHourTarget\`）
- [ ] `ShowAction`（`LearningHourTargetService::compute` 同梱）（REQ-learning-090）
- [ ] `UpsertAction`（`withTrashed + lockForUpdate + restore + UPDATE / 新規 INSERT` 分岐）（REQ-learning-091, REQ-learning-098, NFR-learning-001）
- [ ] `DestroyAction`（冪等 SoftDelete）（REQ-learning-092, NFR-learning-001）

### Service（`App\Services\`）
- [ ] `SessionCloseService`（`closeOpenSessions` / `closeOne` / `closeStaleSessions`、`DB::transaction()` 非保有）（REQ-learning-041, REQ-learning-042, REQ-learning-047, REQ-learning-049, NFR-learning-005）
- [ ] `ProgressService`（`summarize(Enrollment): ProgressSummary` + `ratio()` private、単一 JOIN クエリ）（REQ-learning-060, REQ-learning-061, REQ-learning-062, REQ-learning-063, REQ-learning-064, REQ-learning-065, REQ-learning-066, REQ-learning-067, NFR-learning-002）
- [ ] `ProgressSummary` DTO（readonly class、`app/Services/ProgressSummary.php`）（REQ-learning-061）
- [ ] `StreakService`（`calculate(User): StreakSummary`、`DISTINCT DATE + CONVERT_TZ` クエリ + 連続日数計算）（REQ-learning-080, REQ-learning-081, REQ-learning-082, REQ-learning-083, REQ-learning-084, REQ-learning-085, REQ-learning-086, NFR-learning-002）
- [ ] `StreakSummary` DTO（readonly class）（REQ-learning-080）
- [ ] `LearningHourTargetService`（`compute(Enrollment): LearningHourTargetSummary`、target / studied / remaining 集計 + ゼロ除算ガード）（REQ-learning-094, REQ-learning-095, REQ-learning-096, REQ-learning-099, NFR-learning-002）
- [ ] `LearningHourTargetSummary` DTO（readonly class）（REQ-learning-094）
- [ ] `StagnationDetectionService`（`isStagnant` / `detectStagnant` / `lastActivityAt`、`whereDoesntHave` 単一クエリ + 固定 Eager Loading、[[notification]] Schedule Command と [[dashboard]] 双方から消費される契約）（REQ-learning-120, REQ-learning-121, REQ-learning-122, REQ-learning-123, REQ-learning-124, REQ-learning-125, REQ-learning-126, NFR-learning-008）

### ドメイン例外（`app/Exceptions/Learning/`）
- [ ] `SectionUnavailableForProgressException`（HTTP 409、`ConflictHttpException` 継承）（REQ-learning-023, NFR-learning-004）
- [ ] `EnrollmentInactiveException`（HTTP 409、`ConflictHttpException` 継承）（REQ-learning-024, REQ-learning-048, REQ-learning-146, NFR-learning-004）
- [ ] `LearningHourTargetInvalidException`（HTTP 422、`UnprocessableEntityHttpException` 継承、FormRequest 二重ガード用）（REQ-learning-093, NFR-learning-004）

### Schedule Command
- [ ] `App\Console\Commands\Learning\CloseStaleSessionsCommand`（signature: `learning:close-stale-sessions`、`CloseStaleSessionsAction` を呼ぶ薄いラッパー）（REQ-learning-047）
- [ ] `App\Console\Kernel::schedule()` に `$schedule->command('learning:close-stale-sessions')->dailyAt('00:30')` を追加（REQ-learning-047）

## Step 5: Blade ビュー + JavaScript

### Blade（`resources/views/learning/`）
- [ ] `index.blade.php`（受講中 Enrollment カードグリッド、現在ターム / 進捗 / カウントダウン / 最終学習日表示）（REQ-learning-010）
- [ ] `enrollments/show.blade.php`（資格別 Part 一覧 + 進捗ゲージ + ストリーク + 学習時間目標サマリ）（REQ-learning-011）
- [ ] `parts/show.blade.php`（Part 詳細 + Chapter 一覧 + Chapter 別進捗バー）（REQ-learning-012）
- [ ] `chapters/show.blade.php`（Chapter 詳細 + Section 一覧 + 読了バッジ + Quiz 遷移リンク）（REQ-learning-013）
- [ ] `sections/show.blade.php`（パンくず + 本文 HTML + 読了マークトグル + Quiz 遷移 + 学習セッショントラッカー埋込）（REQ-learning-014, REQ-learning-015）
- [ ] `sections/_partials/markdown-body.blade.php`（`{!! $body_html !!}` ラッパー、Service 出力の信頼済 HTML）（REQ-learning-014）
- [ ] `sections/_partials/read-toggle.blade.php`（`@can` で表示制御、JS トグル発火点）（REQ-learning-020, REQ-learning-022）
- [ ] `sections/_partials/session-tracker.blade.php`（`<div data-section-id data-start-url data-stop-url-template>` で JS パラメータ注入）（REQ-learning-015, REQ-learning-045）
- [ ] `hour-targets/_partials/form.blade.php`（`target_total_hours` 数値入力フォーム、PUT 送信）（REQ-learning-091, REQ-learning-093）
- [ ] `hour-targets/_partials/summary-card.blade.php`（残り時間 / 残り日数 / 日次推奨ペース表示 + target 未設定時 CTA）（REQ-learning-094, REQ-learning-095, REQ-learning-096）
- [ ] パンくずナビ共通部品: `_partials/breadcrumb.blade.php`（Enrollment > Part > Chapter > Section）

### JavaScript（`resources/js/learning/`）
- [ ] `session-tracker.js`（`DOMContentLoaded` で `POST /learning/sessions/start` 発火、`pagehide` / `beforeunload` / `visibilitychange = hidden` で `navigator.sendBeacon` 経由 `PATCH /learning/sessions/{id}/stop` 発火、Wave 0b の `utils/fetch-json.js` 利用）（REQ-learning-015, REQ-learning-016, REQ-learning-045, NFR-learning-007, NFR-learning-009）
- [ ] `mark-read-toggle.js`（読了マークボタン click で `POST` / `DELETE /learning/sections/{id}/read` 発火、UI 即時更新、CSRF token 付き fetch）（REQ-learning-020, REQ-learning-022, NFR-learning-007）
- [ ] `vite.config.js` に上記 2 ファイルを entry 追加（または `resources/js/learning/index.js` 経由で集約 import）

## Step 6: テスト

### Feature テスト（Controller 単位、`tests/Feature/Http/`）
- [ ] `Browse/IndexTest.php`（受講中 + 休止 Enrollment 表示、passed/failed は除外、他者 Enrollment 不表示、coach/admin は 403）
- [ ] `Browse/ShowEnrollmentTest.php`（自分の Enrollment OK、他者 403、SoftDelete 済 Enrollment 404）
- [ ] `Browse/ShowPartTest.php`（受講登録あり + 公開済 OK、非公開 Part 404、未登録資格 403）
- [ ] `Browse/ShowChapterTest.php`（受講登録あり + 公開済 OK、非公開 Chapter / 親 Part Draft 404）
- [ ] `Browse/ShowSectionTest.php`（受講登録あり + 公開済 OK、Draft 404、Markdown HTML 化確認、SectionProgress 既存時バッジ表示確認）
- [ ] `SectionProgress/MarkReadTest.php`（正常系 INSERT、再マークで UPDATE、SoftDeleted 復元、Draft Section 409、Enrollment `paused` OK / `passed` / `failed` で 409、未登録資格 403、他者 Section 403）
- [ ] `SectionProgress/UnmarkReadTest.php`（正常系 SoftDelete、もとから読了無し 204 冪等、他者 Section 403）
- [ ] `LearningSession/StartTest.php`（正常系 INSERT、既存未終了 Session 自動クローズ確認、`Enrollment.status=passed` / `status=failed` で 409、`status=learning` / `paused` で OK、他者 Section 403、不正 `section_id` 422）
- [ ] `LearningSession/StopTest.php`（正常系 UPDATE、既終了 Session 冪等 200、他者 Session 403、`duration_seconds` 1 秒以上保証、`max_session_seconds` 超過時クランプ）
- [ ] `LearningHourTarget/ShowTest.php`（自分 OK、coach 担当 OK、coach 他担当 403、admin OK、他 student 403、target 未設定時 `target_total_hours=0` 返却）
- [ ] `LearningHourTarget/UpsertTest.php`（新規 INSERT、UPDATE、SoftDeleted 復元 + UPDATE、coach/admin 403、`target_total_hours: 0` で 422、`10000` で 422、`-1` で 422、文字列で 422、他者 Enrollment 403）
- [ ] `LearningHourTarget/DestroyTest.php`（正常系 SoftDelete、もとから無し 冪等、他者 403）

### UseCase テスト（`tests/Feature/UseCases/`、複雑なケース）
- [ ] `Learning/ShowEnrollmentActionTest.php`（Part 一覧 + ProgressService 同梱の Eager Loading）
- [ ] `SectionProgress/MarkReadActionTest.php`（restore + UPDATE 分岐、Draft 連鎖時の Exception、Enrollment 状態別の Exception）
- [ ] `LearningSession/StartActionTest.php`（既存未終了 Session の自動クローズ動作 + 新規 INSERT のアトミック性、Enrollment 状態判定境界）
- [ ] `LearningSession/StopActionTest.php`（冪等性、`max_session_seconds` clamp、1 秒下限）
- [ ] `LearningSession/CloseStaleSessionsActionTest.php`（4 時間超過 Session の一括クローズ + 4 時間未満は対象外）
- [ ] `LearningHourTarget/UpsertActionTest.php`（SoftDeleted 復元、UNIQUE 制約下での upsert アトミック性）

### Unit テスト（`tests/Unit/`）
- [ ] `Services/SessionCloseServiceTest.php`（`closeOpenSessions` / `closeOne` / `closeStaleSessions` の状態遷移 + max クランプ + 1 秒下限）
- [ ] `Services/ProgressServiceTest.php`（公開済のみカウント、Draft / SoftDelete 除外、ゼロ件時 0.0 返却、Chapter / Part の AND 完了判定、複数資格混在時の絞込）
- [ ] `Services/StreakServiceTest.php`（活動なし時 0、今日のみ 1、今日と昨日連続 2、間飛びリセット、`longest_streak` 算出、タイムゾーン考慮）
- [ ] `Services/LearningHourTargetServiceTest.php`（target 未設定時の各フィールド null、target あり remaining 正、studied > target 時 remaining 負、`exam_date` 超過時 remainingDays=0、`progress_ratio` 1.0 上限、ゼロ除算ガード）
- [ ] `Services/StagnationDetectionServiceTest.php`（活動なし learning Enrollment は stagnant、7 日以内 active なら非 stagnant、`paused` / `passed` / `failed` は対象外、`whereDoesntHave` で N+1 起こさない確認）
- [ ] `Policies/SectionProgressPolicyTest.php`（ロール × 操作の真偽値網羅）
- [ ] `Policies/LearningSessionPolicyTest.php`（同上）
- [ ] `Policies/LearningHourTargetPolicyTest.php`（同上）
- [ ] `Policies/PartViewPolicyTest.php` / `ChapterViewPolicyTest.php` / `SectionViewPolicyTest.php`（受講登録あり / なし、状態別の真偽値網羅）

## Step 7: 動作確認 & 整形

- [ ] `sail artisan test --filter=Learning` 通過（全 Feature / UseCase / Unit テスト）
- [ ] `sail bin pint --dirty` 整形
- [ ] 受講生フロー動作確認:
  1. `/learning` に受講中資格カードが並ぶ / 試験日カウントダウン / 直近活動日が表示される
  2. カードクリックで `/learning/enrollments/{enrollment}` → Part 一覧 + 進捗ゲージ + ストリーク表示
  3. Part 詳細 → Chapter 詳細 → Section 詳細と階層遷移できる
  4. Section 詳細で Markdown 本文が画像込みで描画される
  5. ブラウザのネットワークタブで `/learning/sessions/start` が DOMContentLoaded で発火しているのを確認
  6. ページ離脱（タブ閉じ / 別 URL 遷移 / 別 Section 遷移）で `/learning/sessions/{id}/stop` が `sendBeacon` で発火しているのを確認（DevTools → Network → Beacon フィルタ）
  7. 読了マークボタンを押す → 進捗ゲージが即時更新される（または再読込で更新される）
  8. 読了取消ボタンを押す → 進捗が戻る
  9. `/learning/enrollments/{enrollment}/hour-target` で学習時間目標を 50 時間に設定 → ダッシュボードに残り時間 / 日次推奨ペースが表示される
  10. 学習時間目標を削除 → 「未設定」状態の CTA に戻る
- [ ] coach / admin の挙動確認:
  1. coach が `/learning/*` URL に直接アクセス → 403（`role:student` Middleware）
  2. admin も同様に 403
  3. dashboard 経由で coach / admin が担当受講生の進捗 / ストリーク / 滞留検知を閲覧できる（[[dashboard]] 実装後に再確認）
- [ ] Schedule Command 動作確認:
  1. `sail artisan learning:close-stale-sessions` を手動実行
  2. Factory で 4 時間以上前の未終了 LearningSession を作成 → Command 実行で `ended_at` / `duration_seconds` / `auto_closed=true` がセットされることを確認
  3. 4 時間未満の未終了 Session が対象外であることを確認
- [ ] [[notification]] / [[dashboard]] 連携確認（依存 Feature 実装後）:
  1. notification の `notifications:send-stagnation-reminders` Schedule Command が本 Feature の `StagnationDetectionService::detectStagnant()` を呼ぶ
  2. 7 日学習途絶受講生に通知が dispatch される
  3. dashboard 滞留検知リストに同受講生が表示される
- [ ] 性能確認: `sail artisan tinker` で 100 Enrollment × 1000 LearningSession 規模のシード後、`ProgressService::summarize` / `StagnationDetectionService::detectStagnant` / `StreakService::calculate` が 100ms 以下で完了することを確認（`DB::enableQueryLog()` で発行クエリ数も確認、各 Service 1〜2 クエリ以内）
- [ ] N+1 確認: dashboard / `/learning` 系画面で `DB::listen()` を仕込み、`with` Eager Loading 漏れがないことを確認（NFR-learning-006）
