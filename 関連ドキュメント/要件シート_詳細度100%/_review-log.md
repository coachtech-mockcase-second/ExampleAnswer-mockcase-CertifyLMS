# 詳細化フェーズ 模範解答 PJ 懐疑的レビュー ログ

各チケット詳細化時に対応する模範解答 PJ 実装をレビューした結果を集約。Phase 7(詳細化完了後)で一括修正・規約確定に活用。

## 凡例

- ✅ 適切 — 現状で問題なし
- ⚠️ 修正必要 — チケット内で対応 or 即時修正
- 🕐 Phase 7 で一括処理 — 他 Feature と横断的に判断する必要あり、詳細化完了後にまとめて対応

---

## S-B-01 / qa-board-01: 質問掲示板の実装

### レビュー対象

- `模範解答プロジェクト/app/Http/Controllers/{QaThreadController,QaThreadModerationController}.php`
- `模範解答プロジェクト/app/UseCases/QaThread/*Action.php`(7 本)+ `Moderation/*Action.php`(3 本)
- `模範解答プロジェクト/app/Policies/QaThreadPolicy.php`
- `模範解答プロジェクト/resources/views/qa-thread/`(8 + 2 ファイル)
- `模範解答プロジェクト/database/migrations/{date}_create_{qa_threads,qa_replies}_table.php`
- `模範解答プロジェクト/app/Enums/QaThreadStatus.php`
- `模範解答プロジェクト/app/Exceptions/QaBoard/{QaThreadHasReplies,QaThreadAlreadyResolved,QaThreadNotResolved}Exception.php`

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| 画面分離(student/coach 共通 + admin 別) | ✅ 適切 | admin の責務(モデレーション削除 / SoftDelete トグル / 全資格選択肢)は他ロールと大きく異なる。共通化すると UX 混乱、実装通りの分離が業界標準 |
| Policy 認可判定(ロール + 当事者 + 担当資格) | ✅ 適切 | `QaThreadPolicy::view` で coach の `coachingCertificationIds()` チェック。`delete` は人ベース認可(投稿者本人 OR admin)、回答有無の削除可否は `DestroyAction` の `QaThreadHasRepliesException`(409)に分離(2026-05-28 修正、下記「delete の認可と状態ガードを責務分離」参照)|
| status Enum + resolved_at 二本立て + 同時更新 | ✅ 適切 | 他 Feature(`Enrollment.status + passed_at` 等)と整合、`tech.md` 規約準拠 |
| 通知発火位置(StoreAction → 通知ラッパー経由) | ✅ 適切 | 自己回答スキップを notification 側に寄せる責務分担は REQ-qa-board-111 と一致 |
| 列挙攻撃防御(担当外資格 → 403) | ✅ 適切 | NFR-qa-board-006 通り、403 で「担当外であることを明示」 |
| **Controller 命名揺らぎ** | 🕐 Phase 7 | spec = `Admin\QaThreadController` / 実装 = `QaThreadModerationController`。Feature 全体で命名規約統一が必要、他 Feature も横断調査して規約確定 |
| **View ディレクトリ名揺らぎ** | 🕐 Phase 7 | Feature 名 / URL = `qa-board` だが View = `qa-thread/`。同様に Phase 7 で全 Feature 横断確認後リネーム |

### delete の認可と状態ガードを責務分離(2026-05-28、模範解答 PJ 修正)

⚠️→対応完了。`/ticket-detail-100p S-B-01` 再生成時の実コード再確認で、スレッド削除の「認可(人ベース)」と「状態ガード(回答あり = 削除不可)」が癒着していた不整合を検出し、模範解答 PJ を修正。

**検出した不整合**:

- `QaThreadPolicy::delete` が「投稿者本人 **かつ回答 0 件** OR admin」を判定 → 投稿者が回答ありスレッドを削除しようとすると Policy が **403 で先取り**(`@can('delete')` で削除ボタンも非表示)。
- `DestroyAction` の `QaThreadHasRepliesException`(409)+ Handler の redirect+flash 変換は用意されていたが、HTTP 経路では Policy の 403 が先に返るため **到達不能な死にコード**(直接 Action を呼ぶ Unit テストでのみ 409 を観測)。
- チケット(AC / 異常系 / Q&A)・Handler・例外クラスは 409 + フラッシュ「回答が付いているスレッドは削除できません。」を前提にしていたが、実際の観測挙動は 403(標準 403 ページ、フラッシュなし)で食い違い。
- 同 PJ の MeetingPack / Plan 削除は「Policy = 人ベース認可 / Action = 状態ベースガード(公開中削除不可 → 409)」で責務分離済(本ログ該当節)。qa-board の delete だけ「回答 0 件」状態チェックを Policy に混ぜており、PJ 自身の確立パターンから逸脱していた。

**修正(模範解答 PJ)**:

- `QaThreadPolicy::delete` から `&& $thread->replies()->doesntExist()` を除去 →「admin OR 投稿者本人」の人ベース認可のみに。回答有無は `DestroyAction`(回答ありで `QaThreadHasRepliesException` 409)に一本化 → MeetingPack / Plan と同じ Policy(人)/ Action(状態)責務分離に統一、死にコード解消 + 削除理由がフラッシュ表示される UX に改善。
- DocBlock 更新: `QaThreadPolicy` / `QaThreadController` / `QaReply` の delete 記述を「状態ガードは Action」に修正。
- テスト更新: `DestroyTest`(回答あり投稿者削除 403→**409**)/ `QaThreadPolicyTest`(投稿者は回答有無に関わらず Policy 通過 = true)。`DestroyActionTest`(409)は既存維持。
- 検証: qa-board 関連 **83 テスト全 pass**(SQLite in-memory、175 assertions)。
- チケット S-B-01 は 409 のまま正となり、AC / インターフェース / データモデル / 設計判断 / Q&A を「Policy = 人 / Action = 状態」表現に整合。

---

## Phase 7 で扱う横断課題まとめ → ✅ 対応完了(2026-05-25)

詳細化中に「他 Feature との整合性が必要」と判定された課題。Phase 7(全件詳細化完了後)に再評価した結果、いずれも **既に規約 + 実装ともに整合済**(S-B-01 詳細化当時の揺らぎはその後の実装整理で解消)。

| # | 課題 | 関連チケット | 対応結果 |
|---|---|---|---|
| 1 | Controller 命名規約(`Admin\` サブディレクトリ vs `Moderation` 接尾辞) | S-B-01 ほか admin 機能を持つ全 Feature | ✅ **対応完了**(2026-05-25)。`backend-http.md` で「ロール別 namespace 禁止 / フラット推奨 / 領域別 namespace は `Auth\` / `Webhooks\` / `Settings\` / `Api\V1\` のみ許容」と明文化済。実装も全 53 Controller がフラット or 上記領域別 namespace のみで、`Admin\` サブディレクトリ・`Moderation` 接尾辞は存在しない。当時揺らぎがあった qa-board も `QaThreadController` 1 本(index/show/create/store/edit/update/destroy/resolve/unresolve)に統合済(commit `e79e849 refactor: qa-board/chat の Moderation 構造を共通化し qa-board を Basic 教材化`)→ リネーム不要、規約追記不要 |
| 2 | View ディレクトリ名規約(Feature 名 vs エンティティ名) | S-B-01 ほか | ✅ **対応完了**(2026-05-25)。`frontend-blade.md` で「トップディレクトリ名 = Eloquent Model 名の単数 kebab-case」「view 名と route 名は意図的にずれる(view = 内向け Entity 単位 / URL = 外向け Feature 慣習)」と明文化済 + Model→view の対応表に `QaThread → qa-thread/`(URL `/qa-board`)等を例示。実装も `qa-thread/` `meeting-pack/` `plan/` `chat-room/` 等が Model 単数 kebab-case で整合 → リネーム不要、規約追記不要 |

---

## テンプレ + 規約刷新(2026-05-28、実装方針サブセクションを MECE な 5 構造に再設計)

### 経緯

S-B-01 詳細化レビュー時に「実装方針(参考)の冗長性」がユーザーから指摘され、業界標準(Google Design Doc / Kiro SDD / Atlassian PRD / IEEE 830 / Spotify RFC)を調査して MECE な必要最低限構造を再設計。テンプレ 3 種(`templates/{story,bug,task}.md`)と規約(`.claude/rules/ticket-spec.md` §1 / §2.1 / §2.4 / Task 専用節)を一括刷新。

### 旧 7 → 新 5 サブセクション(Story)

| 旧 Kiro 順序 7 | 新構造 5 |
|---|---|
| コンポーネント | **削除** → 順序 3「コンポーネント」(命名維持、クラス + ファイルパス + 1 行責務を集約する SSoT に格上げ) |
| データモデル | **順序 2 に維持**(エンティティ表に「制約」列を追加、FK / SoftDelete / 削除戦略を集約) |
| インターフェース | **順序 1 に格上げ**(エンドポイント表に「認可」列を追加、認可マトリクスを統合) |
| エラーハンドリング | **「異常系」に改名**(順序 4、What 寄り命名で他 4 つと統一) |
| テスト戦略 | **独立節を廃止** → 設計判断節に「テスト観点」として 1〜2 行で内包 |
| 実装アプローチ | **「設計判断」に改名**(順序 5、Why セクション。Basic 共通前提は書かない) |
| 関連ファイル | **削除** → コンポーネント節に統合(クラス列挙の SSoT を 1 箇所に集約) |

### 順序の論理(外部→内部の潜行順)

直前の「受け入れ条件」=振る舞いの観察可能事象、直後の「インターフェース」=振る舞いの技術契約で連続性を保つ。次に「何が永続化される(データモデル)→ どこに何の実装がある(コンポーネント)→ 異常時はどうなる(異常系)→ なぜこの作り(設計判断)」と外側から内側へ潜る。

### MECE な SSoT 集約

| 情報カテゴリ | 唯一の置き場所 |
|---|---|
| 認可ルール | インターフェース表の `認可` 列のみ(認可マトリクス独立節は廃止) |
| クラス名 + ファイルパス + 責務 | コンポーネント節のみ(他セクションで再列挙しない) |
| 削除戦略 / 外部キー / SoftDelete | データモデル エンティティ表の `制約` 列のみ |
| エラー文言推奨例(日本語) | 想定 Q&A のみ(異常系節は機械的ルール記法のみ) |
| Basic 共通前提(JS なし / XSS / Pint) | **書かない**(`.claude/rules/` 既存ガイドに委譲) |

### Bug / Task テンプレへの波及

- **Bug**: 実装方針は「原因」のみ(変更なし)、受け入れ条件にテスト実装 AC を追記
- **Task**: 「変更内容 + 設計判断」の 2 サブセクション、独立した「関連ファイル」節を廃止し変更内容に統合

### 参考ベストプラクティス

[Design Docs at Google](https://www.industrialempathy.com/posts/design-docs-at-google/) / [Kiro Specs](https://kiro.dev/docs/specs/) / [Atlassian PRD Template](https://www.atlassian.com/software/confluence/templates/product-requirements) / [Pragmatic Engineer "Companies Using RFCs or Design Docs"](https://blog.pragmaticengineer.com/rfcs-and-design-docs/) / [IEEE 830 Template](https://press.rebus.community/requirementsengineering/back-matter/appendix-c-ieee-830-template/) / [Tyner Blain "Requirements vs Design"](https://tynerblain.com/blog/2006/02/11/requirements-vs-design-which-is-which-and-why/) を踏まえ、「コードを書き換えても残る記述 = What(仕様)/ 書き換えたら陳腐化する記述 = Why(設計判断)」の境界線で再構成。

### 影響範囲

- `.claude/skills/ticket-detail-100p/templates/story.md` — 全面書き換え
- `.claude/skills/ticket-detail-100p/templates/bug.md` — 微修正(テスト実装 AC 追加)
- `.claude/skills/ticket-detail-100p/templates/task.md` — 設計判断節追加、関連ファイル節廃止
- `.claude/rules/ticket-spec.md` — §1 セクション構成表 / §2.1 セクション別記載範囲表 / §2.4 サブセクション粒度ガイド / Task 専用節 を更新
- `S-B-01_qa-board-01.md` — 新構造で再々生成
- **既存 40 件のチケット** — 次回詳細化時に随時新構造へ移行(全件即時書き直しはしない)

---

## S-A-05 / S-B-04 / S-B-05 / S-B-09 再々生成(2026-05-28、実装方針 5 サブセクション構造で書き直し)

S-B-01 と同じ新構造(インターフェース → データモデル → コンポーネント → 異常系 → 設計判断)で 4 ファイルを再々生成。各ファイルとも:

- インターフェース表に **認可列を追加**(認可マトリクス独立節を廃止 → 表内 1 セルに圧縮)
- データモデル エンティティ表に **制約列を追加**(FK 戦略 / SoftDelete / 削除戦略を 1 箇所に集約)
- コンポーネント節を独立大セクションに(関連ファイル節を統合、クラス名 + ファイルパス + 1 行責務をレイヤー別グルーピングで集約)
- エラーハンドリング → 異常系に改名、推奨日本語文言は Q&A に集約済
- テスト戦略独立節を廃止、設計判断節内の 1 行「テスト観点」で各チケット固有の観点のみ言及
- Basic 共通前提(JS なし / XSS 防御 / Pint 整形 等)を設計判断から削除(`.claude/rules/` に委譲)

→ 既存 40 件のうち刷新版 4 件 + S-B-01 を含む合計 5 件が新 5 構造に移行。残り 35 件は次回詳細化時に随時新構造へ移行(全件即時書き直しはしない)。

---

## mentoring 系 7 件 再生成(2026-05-28、新粒度 = 実装方針サブセクション構造 + フラット AC で書き直し)

S-A-01 / S-B-08(Story)/ B-A-01(Bug)/ T-A-01 / T-A-02 / T-A-03 / T-B-02(Task)の mentoring ドメイン 7 件を、2026-05-27〜28 の規約・テンプレ刷新(やること/やらないこと → 要件/スコープ外 / Story 実装方針 5 サブセクション[インターフェース → データモデル → コンポーネント → 異常系 → 設計判断] / Bug 実装方針 = 原因のみ / Task 実装方針 = 変更内容のみ / AC フラット化 + テスト実装 AC 追加 / Q&A フラットテーブル / HTML コメント粒度ヘッダ削除)に合わせて再生成。Step 3 模範解答 PJ 懐疑的レビューは既存判定(2026-05-25 の各エントリ = いずれも ✅ 適切)を尊重しスキップ。AC 件数: S-A-01 = 10(規模大上限)/ S-B-08 = 7(通常上限)/ B-A-01 = 4(3 + テスト)/ T-A-01・T-A-02・T-A-03 = 3 / T-B-02 = 2(目的 + テスト)。

### 模範解答 PJ コードとの整合確認で見つかった既存版チケットの誤り(再生成で訂正)

- **B-A-01 の面談予約 URL**: 旧版「`POST /meetings/enrollments/{enrollment}`」は誤り。`routes/web.php` の `Route::prefix('enrollments/{enrollment}')` group 内 `Route::post('meetings', ...)` = **`POST /enrollments/{enrollment}/meetings`**(route 名 `meetings.store`)。訂正済
- **T-A-03 の連携開始 URL**: 旧版「`GET /settings/google-calendar/redirect`」は controller method 名 `redirect` を URL と取り違えた誤り。`Route::get('connect', [CoachGoogleCredentialController::class, 'redirect'])` = **URL `/settings/google-calendar/connect` / method `redirect` / route 名 `settings.google-calendar.redirect`**。訂正済(S-A-01 は元から `connect` で正しかった)
- **S-A-01 の面談 Event の URL ソース**: 旧版「`users.meeting_url` を Event に焼き込む」は、実装では `GoogleCalendarService::insertEvent` が `meeting->meeting_url_snapshot`(予約成立時に面談へスナップショットされたコーチ固定 URL)を使用。予約後にコーチがプロフィール URL を変えても Event 内容は予約時点で固定される旨を Q&A に明記して訂正

### コード整合の再確認(主要事実、各チケットのコンポーネント / 変更内容 / 原因節 SSoT に反映)

- `MeetingController` は既に薄い(`store`/`cancel`/`upsertMemo` が Action を DI して呼ぶだけ)= T-A-02 のリファクタ完成形と一致(提供 PJ では Step 4 で Controller にロジックを戻す)
- `ConsumeQuotaAction::__invoke` の `DB::transaction` 冒頭 `User::query()->whereKey($user->id)->lockForUpdate()->first();`(B-A-01 の Step 4 削除対象 1 行)を確認
- `MeetingQuotaService::remaining` = `max_meetings + SUM(amount WHERE type IN [Consumed,Refunded,Purchased,AdminGrant])`、`granted_initial` は二重計上回避で集計除外(B-A-01 Q&A 整合)
- `FetchCoachDashboardAction` の `->with(['user', 'certification'])->withMax('learningSessions as last_activity_at', 'started_at')`(T-B-02 の Step 4 削除対象 2 行)を確認
- `MeetingAvailabilityService::slotsForCertification` = `coaches()->with('googleCredential')` + `CoachAvailability` / `Meeting` の `whereIn` 一括取得 + 連携済コーチのみ freebusy = DB 4 本固定(T-A-01 の After 形)を確認
- `GoogleOAuthService`(buildClient / getAuthUrl / exchangeCode / revoke、final 不採用)/ `GoogleCalendarService`(freebusy / insertEvent / deleteEvent + private refresh、final 不採用)/ `GoogleOAuthException`(400、`stateMismatch` / `missingRefreshToken`)/ `CoachGoogleCredential\StoreAction`(state.coach_id 照合 → code 交換 → upsert)/ `EnrollmentNotePolicy`(`canAccessEnrollmentForNotes` / `canModify`)/ `enrollment_notes`(enrollment_id cascade + coach_user_id restrict + (enrollment_id, created_at) / coach_user_id index)・`coach_google_credentials`(coach_id UNIQUE cascade + token カラム + connected_at)・`meetings`(google_event_id nullable + UNIQUE(coach_id, scheduled_at) + meeting_url_snapshot)migration を確認

→ ✅ 7 件すべて新構造へ移行完了。模範解答 PJ コードとの 1:1 対応を再確認し、URL 系の既存誤り 3 点を訂正。Step 4 引き算 SSoT として実装方針(コンポーネント / 変更内容 / 原因)節で機械的判断が可能。

---

## S-B-01 / qa-board-01 再々生成(2026-05-28、実装方針 5 サブセクション構造で書き直し)

新 5 サブセクション構造(インターフェース → データモデル → コンポーネント → 異常系 → 設計判断)で全面再生成。主な変更:

- インターフェース表に「認可」列を追加(認可マトリクス独立節を廃止 → 表内 1 セルに圧縮)
- データモデル エンティティ表に「制約」列を追加(FK 戦略 / SoftDelete / 2 段階削除を 1 箇所に集約)
- コンポーネント節を独立大セクションに(関連ファイル節を統合、クラス名 + ファイルパス + 1 行責務をレイヤー別グルーピングで集約)
- エラーハンドリング → 異常系に改名、推奨日本語文言は Q&A に集約済
- テスト戦略独立節を廃止、設計判断節内の 1 行「テスト観点」で qa-board 固有の Policy 三重判定 + 列挙攻撃のみ言及
- Basic 共通前提(JS なし / XSS 防御 / 検索 LIKE)を設計判断から削除(`.claude/rules/` に委譲)

→ 行数: 308 行(2026-05-27 版) → 約 250 行(本版)、約 60 行削減

---

## S-B-07 / B-B-08 / B-B-07 / S-A-04 再生成(2026-05-28、新テンプレ構造で書き直し)

Story 2 件(`S-B-07` settings-profile-01 / `S-A-04` certification-management-01)を新 5 サブセクション構造(インターフェース → データモデル → コンポーネント → 異常系 → 設計判断)で、Bug 2 件(`B-B-07` certification-management-02 / `B-B-08` settings-profile-02)を新 Bug テンプレ(原因サブセクション + テスト実装 AC 追記)で再生成。**再生成モードのため Step 3 模範解答 PJ 懐疑的レビューはスキップ**(既存判定を尊重)。ただし再生成前に模範解答 PJ の実コードを再確認し、コンポーネント / 異常系 / 振る舞いを実装と一致させた。

確認した実コード(2026-05-28):

- `Settings\{Profile,Avatar,Password}Controller` — profile update → `?tab=profile` + フラッシュ / avatar store・destroy → `?tab=profile` + フラッシュ / password update → `?tab=password` + `status=password-updated`。`Profile\UpdateAction` は coach 以外の `meeting_url` を silently drop + 空入力 NULL 保存。`UserPolicy::updateSelf` は ID 一致のみ
- `CertificateController::download` は `auth` のみ(`active-learning` 非適用)+ `authorize('download')` → `DownloadAction`。`Certificate\IssueAction` は修了状態ガード → `DB::transaction` 内で `lockForUpdate` 二重発行検出 → 採番 → `CertificatePdfService::generate`(失敗時 Storage 保険削除 + `CertificateGenerationFailedException`)。`CertificatePolicy::download` は admin 全件 / student 本人 / coach 担当資格(`loadMissing('certification.coaches')`)
- `CertificationCategoryController::destroy` は `authorize('delete')` → `DestroyAction` → 一覧 + 成功フラッシュ(store / update も同型、destroy のみのフラッシュ漏れが B-B-07 の自然な仕込み)

主な変更:

- **S-B-07**: AC 23 → 8 件に圧縮(画面表示+アクセス / プロフィール編集 / コーチ固定面談 URL / バリデーション / パスワード変更 / アバター upload / アバター delete / テスト)。Story の `概要` 節を削除(背景・目的に集約)、`やること`→`要件`(技術名漏れを業務語彙化)、`やらないこと`→`スコープ外`。実装方針を 5 サブセクション化、`users` 既存テーブルのためデータモデルは既存カラム + Storage + Seeder 不要を明記
- **S-A-04**: AC 22 → 9 件に圧縮(発行ガード×2 / 発行成功+採番+PDF 実体 / 失敗ロールバック / PDF 内容 / DL 認可 / DL 応答 / PDF 不在 / 学習中以外 / テスト)。`概要` 削除、トップレベルの `Seeder 設計` 節を実装方針 > データモデル > 初期データ Seeder に統合。12 個あった `アーキテクチャ判断` を本チケット固有のトレードオフのみの `設計判断` に整理、クラス列挙はコンポーネント節に集約
- **B-B-07 / B-B-08**: 旧 `主要 URL` + `原因箇所メモ` を新テンプレの `原因`(主要ファイル / 仕込み内容 / 修正範囲)に再構成。期待 / 実際の `動作`→`結果` にリネーム、テスト実装 AC を追記、先頭の HTML コメントを削除

→ 新 5 / 新 Bug 構造への移行: 既存 41 件のうち S-B-01 + S-A-05 / S-B-04 / S-B-05 / S-B-09 + 本バッチ 4 件 = 計 9 件が移行済。残りは次回詳細化時に随時移行。

---

## S-B-01 / qa-board-01 再生成(2026-05-27、規約刷新版で書き直し)

### 経緯

`.claude/rules/ticket-spec.md` の 2026-05-26 規約刷新(Kiro 順序 7 サブセクション + 業務語彙 / 技術名併記の必須化 + AC 圧縮原則 5 パターン + 「やること / やらないこと」→「要件 / スコープ外」のリネーム + 想定 Q&A から設計判断系の除去)に合わせ、S-B-04 / S-B-05 / S-B-09 / S-A-05 と同じ刷新版形式に書き直し。模範解答 PJ コードは commit `e79e849`(qa-board Moderation 構造共通化)以降変更なし、Step 3 模範解答 PJ レビューは既存判定(下記過去ログの ✅ / 🕐 Phase 7 解決済)を尊重しスキップ。

### 主な書き直し点

- **要件セクションの構造化**: 旧「やること(スレッド / 回答 / 閲覧・検索 / 共通)」4 グループを業務語彙ベース 5 グループ(スレッド管理 / 回答管理 / 一覧・検索・詳細 / 管理者モデレーション / アクセス制御)+ 入力検証独立サブセクションに再整理。HTTP ステータス・URL・技術名(Policy / FormRequest 等)を除去し業務語彙のみに統一
- **AC 圧縮**: 21 件 → 10 件(規模大 Story の上限ぎりぎり)。圧縮原則を適用 — スレッド削除(投稿者削除 + 回答ありエラー)を 1 行統合 / 解決マーク(認可 + 状態整合性 + 重複操作 409)を 1 行統合 / 一覧 + 詳細 + 検索 + N+1 を 1 行統合 / アクセス制御 + 公開停止資格 404 を 1 行統合 / 認可拒否を機能群レベルで 1 行統合 / バリデーション全項目をネスト形式で 1 行統合
- **データモデル節の Kiro 化**: 旧版の独立 Seeder 設計セクション(thread_1〜thread_5 シナリオ単位)を削除し、実装方針 > データモデル > 初期データ Seeder(必須)に統合。模範解答 PJ 現状(資格別 5-10 件散布 + 状態網羅 + 固定 student 投稿 + 回答数混在 + 作成日時散らし)に基づいて記述
- **コンポーネント節の技術名併記**: Controller / FormRequest / Action / Policy / Model / Enum の業務語彙 + クラス名併記を必須化、認可マトリクス表で 9 操作 × 3 ロールの判定を集約
- **想定 Q&A の絞り込み**: 設計判断系(「admin と一般ユーザーで Controller / Blade は分離されている?」)を削除、仕様確認(バリデーション / 認可 / 振る舞い / 文言)のみに集中。新規追加: `status` クエリパラメータの値(`unresolved` / `resolved`)、サイドバーバッジ廃止の Q&A
- **依存チケット**: なし(通知発火が `S-B-04` 範囲に移管済のため本チケットは独立)

→ ✅ 規約刷新版として完結。Step 4 引き算時の SSoT として「コンポーネント節 + 関連ファイル節」だけで模範解答 PJ → 提供 PJ 変換の機械的判断が可能

---

## S-B-01 / qa-board-01 追加レビュー(2026-05-23、書き直し時)

### ⚠️→対応完了: サイドバーバッジ機能の廃止 → 模範解答 PJ から削除

- **判定**: ⚠️ 修正必要 → **対応完了**(コード上は既に削除済を 2026-05-25 確認、`_review-log.md` メモのみが残っていた)
- **背景**: S-B-01 書き直し時、ユーザー判断で「コーチ用サイドバーバッジ(担当資格 × 未解決 × 回答 0 件 の件数表示)」を本チケットのスコープから除外。採点上の重要度が低く、機能としても廃止する判断。
- **対応内容**(コード状態):
  - `app/View/Composers/SidebarBadgeComposer.php` の coach 分岐から qa-board 関連の COUNT クエリは **削除済**(commit `e79e849 refactor: qa-board/chat の Moderation 構造を共通化し qa-board を Basic 教材化`)。現状は chat 未読数 (`unattendedChat`) のみを供給し、クラス DocBlock にも「未回答質問 / 当日面談の件数はダッシュボードに集約しており、サイドバーには件数バッジを出さない」と明記済
  - `resources/views/components/nav/sidebar.blade.php` に「質問対応 (N)」バッジ表示は存在せず(grep で 0 件確認、2026-05-25)
  - 関連テスト(`SidebarBadge*` テスト)は存在せず(削除済 or 元々なし)
- **本チケット側**: 受け入れ条件 / 実装方針 / Q&A から全削除済み、やらないことに明示

### ⚠️→対応完了: 通知発火を `notification` Feature(S-B-04)に移管

- **判定**: ⚠️ 修正必要 → **対応完了**(コード上は既に移管済を 2026-05-25 確認、`_review-log.md` メモのみが残っていた)
- **背景**: S-B-01 書き直し時、ユーザー判断で「回答時の通知発火」を本チケットのスコープ外とした。本来 qa-board 側で発火呼び出しを書く実装になっていたが、`notification`(S-B-04)のスコープに移管する。
- **対応内容**(コード状態):
  - 模範解答 PJ で qa-board 側の Action(`app/UseCases/QaThread/*`、`app/UseCases/QaReply/*`)から直接の `Notification::send()` 呼び出しは存在しない
  - `notification` Feature 側のラッパー Action `app/UseCases/Notification/NotifyQaReplyReceivedAction.php` が存在し、`app/UseCases/QaReply/StoreAction` から `__construct` で DI されて `__invoke` 内で呼び出される(自己回答スキップは `NotifyQaReplyReceivedAction` 側の責務、`QaReply\StoreAction` 内コメントにも明記)
  - 通知本体 `app/Notifications/QaBoard/QaReplyReceivedNotification.php` + Unit テスト `tests/Unit/Notifications/QaBoard/QaReplyReceivedNotificationTest.php` も存在
  - 関連 Feature テスト(`tests/Feature/UseCases/QaReply/StoreActionTest`, `tests/Feature/Http/QaReply/StoreTest`)は `Notification::assertSentTo($author, QaReplyReceivedNotification::class)` で副作用検証済
- **本チケット側**: 依存チケット `S-B-04` も解除済み(本チケット内では通知関連を一切扱わない)、受け入れ条件 / やること / 実装方針 / Q&A から全削除済み

---

## S-B-02 / meeting-quota-01: 面談パックマスタ管理(admin マスタ CRUD)

### レビュー対象

- `模範解答プロジェクト/app/Http/Controllers/{MeetingPackController,MeetingPackStatusController}.php`
- `模範解答プロジェクト/app/UseCases/MeetingPack/*Action.php`(8 本: Index / Show / Store / Update / Destroy / Publish / Archive / Unarchive)
- `模範解答プロジェクト/app/Models/MeetingPack.php`
- `模範解答プロジェクト/app/Enums/MeetingPackStatus.php`
- `模範解答プロジェクト/app/Policies/MeetingPackPolicy.php`
- `模範解答プロジェクト/app/Http/Requests/MeetingPack/{Store,Update,Index}Request.php`
- `模範解答プロジェクト/app/Exceptions/MeetingQuota/{MeetingPackNotDeletable,MeetingPackInvalidTransition}Exception.php`
- `模範解答プロジェクト/resources/views/meeting-pack/management/*.blade.php`(4 ファイル)
- `模範解答プロジェクト/database/migrations/2026_05_17_000010_create_meeting_packs_table.php`
- `模範解答プロジェクト/database/seeders/MeetingPackSeeder.php`

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Controller 分離(CRUD / 状態遷移) | ✅ 適切 | `MeetingPackController`(CRUD)+ `MeetingPackStatusController`(publish / archive / unarchive)の二分割で Single Responsibility が明確。1 Controller に集約しても可だが、状態遷移が 3 ルートあるので分離する方が読みやすい |
| Policy(admin 真偽判定のみ) | ✅ 適切 | `delete` メソッドも admin 真偽のみ、状態ベースガード(公開中削除不可)は Action 内で `MeetingPackNotDeletableException` を throw。Policy(人ベース)/ Action(状態ベース)の責務分離規約に準拠 |
| 状態遷移 Enum + 例外設計 | ✅ 適切 | `MeetingPackStatus`(3 値)+ `MeetingPackInvalidTransitionException::forPublish/forArchive/forUnarchive` の static factory でメッセージ責務を例外クラスが所有(`backend-exceptions.md` 規約準拠) |
| 削除制約(公開中ガード) | ✅ 適切(2026-05-28 削除方式を物理削除に訂正) | `DestroyAction` 内で公開中なら `MeetingPackNotDeletableException`(409)、下書き / アーカイブのみ **物理削除**。`MeetingPack` は SoftDeletes trait / `deleted_at` カラムなし。当初本表で「SoftDelete」と記載していたが誤りで、`backend-models.md`「マスタ系で Draft/Published/Archived status を持つ Entity は SoftDelete 不採用」+ 姉妹 `Plan` と一致する物理削除が正。`Handler` の HTML→ redirect+flash 変換も活用 |
| Seeder の状態網羅 | ✅ 適切 | published × 3(回数バリエーション 1 / 5 / 10)+ draft × 1 + archived × 1 で一覧フィルタ・状態遷移ボタン活性条件を実機確認できる構成。受講生が `migrate:fresh --seed` 直後に動作確認可 |
| View ディレクトリ命名(`meeting-pack/management/`) | ✅ 適切 | `frontend-blade.md` の Entity 単数 kebab-case + `management/` サブディレクトリ規約に準拠。Phase 7 横断課題には影響しない |
| 詳細画面の購入履歴セクション | ⚪ 本チケット範囲外 | `Payment` テーブル未作成でも空配列フォールバックで画面破綻なし。S-A-04(Stripe 連携)で `payments` テーブル + データが入った時点で表示が活きる設計 |

→ ✅ 適切(即時修正不要、Phase 7 横断課題もなし)

---

## B-B-01 / content-management-01: コーチ教材管理 403 解禁

### レビュー対象

- `模範解答プロジェクト/app/Policies/{Part,Chapter,Section,SectionQuestion,SectionImage,QuestionCategory}Policy.php`(6 ファイル)
- `docs/specs/content-management/{requirements,design}.md` の認可セクション(REQ-content-management-080〜085)

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Policy のエンティティ単位分離(Part / Chapter / Section / SectionQuestion / SectionImage / QuestionCategory)| ✅ 適切 | 各エンティティの所有 Controller と 1:1 対応で Single Responsibility が明確。教材階層が深く認可分岐がエンティティごとに差分を持つため、1 Policy への集約より読みやすい |
| coach 判定パターンの統一(`$certification->coaches()->where('users.id', $coach->id)->exists()`)| ✅ 適切 | 全 Policy で同一クエリパターン。`Certification::coaches()` リレーションを活用し中間テーブル `certification_coach_assignments` 経由で SQL 1 本で判定 |
| ロール × エンティティの `match` 分岐(admin / coach / student / default false)| ✅ 適切 | `UserRole` Enum を活用しマジック文字列禁止。default false で安全側に倒す。Section / SectionQuestion は student の閲覧条件(Published cascade / 受講登録あり)を Policy 内で明示 |
| 状態ベースガード(公開済の削除不可・公開時の選択肢検証)を Policy ではなく Action / Controller の例外で実装する責務分離 | ✅ 適切 | Policy = 人ベース / Action = 状態ベースの責務分離規約(`backend-policies.md`)準拠。本 Bug 修正で Policy を直しても状態ベースガードに影響しない |
| ヘルパーメソッド `assignedCoach()` の有無の揺らぎ | ⚪ 軽微 | Part / Chapter / Section / SectionQuestion は別ヘルパー定義、SectionImage / QuestionCategory は `canManage()` 内に直接埋め込み。読みやすさは両形式とも保たれているため即時是正しない。Phase 7 で全 Policy 横断の DRY 化を再検討する場合に拾い直す候補(現時点では Phase 7 横断課題にはしない) |
| Step 4 引き算の仕込み方(coach 判定を `false` 固定に書き換え) | ✅ 適切 | Middleware(`role:admin,coach`)はそのまま、Policy の coach 分岐のみ破壊するパターン。受講生は admin で動くこと / coach だけ落ちることから「Policy のリソース固有判定」を疑う流れになり、`backend-policies.md` の Middleware vs Policy 役割分担を実機で学ばせる教材的な切り出しになっている |

→ ✅ 適切(即時修正不要、Phase 7 横断課題もなし)

---

## B-B-02〜B-B-16: Bug Basic 残り 15 件 一括詳細化(2026-05-24)

### レビュー対象

各 Bug に対応する模範解答 PJ 実装(Action / Controller / FormRequest / Policy / Middleware / Service / routes)を並列調査(5 グループ)。バグ仕込み箇所のコード・主要 URL・正しい振る舞い・Basic 範囲(Controller/FormRequest/Policy/Middleware/Fortify Action = Basic、Action/Service = ※ 注記)を確認。

### 結論サマリ

| ID | Feature | レビュー | バグ箇所のレイヤー | 備考 |
|---|---|---|---|---|
| B-B-02 | content-management | ✅ 適切 | ※ Action(`Part\IndexAction`)| `ordered()` を各 Action で明示する設計、admin/learning 両系統で整合 |
| B-B-03 | content-management/learning | ⚠️→**対応完了** | ※ Action | 後述(模範解答に正解像を追加実装) |
| B-B-04 | user-management | ✅ 適切 | ※ Action(`User\IndexAction`)| 退会=SoftDelete、退会フィルタ時のみ `withTrashed()` の条件分岐が正 |
| B-B-05 | user-management/auth | ✅ 適切 | ※ Action(`Auth\IssueInvitationAction`)| 入力検証に unique なし、重複は Action ガードが唯一のチェック |
| B-B-06 | auth | ✅ 適切 | ※ Action(`Auth\OnboardAction`)| 招待使用済み化 = `forceFill(status=accepted, accepted_at)` |
| B-B-07 | certification-management | ✅ 適切 | Basic(Controller) | store/update は正常、destroy のみフラッシュ漏れの非対称が自然 |
| B-B-08 | settings-profile | ✅ 適切 | Basic(Controller) | `ProfileController::update` のリダイレクト先誤り。🕐 後述(S-B-07 重複) |
| B-B-09 | content-management/learning | ✅ 適切 | Basic(Controller) | `BrowseController` の `authorize` 呼び出し 3 行削除。Policy ロジックは正 |
| B-B-10 | meeting-quota | ✅ 適切 | ※ Action(`Meeting\CancelAction`)| 残数返却 = `RefundQuotaAction` 呼び出し。Tx 内で原子的 |
| B-B-11 | plan-management | ✅ 適切 | ※ Action(`Plan\IndexAction`)| status フィルタの enum 値コピペミス。🕐 後述(S-B-03 重複)|
| B-B-12 | auth | ✅ 適切 | ※ Action(`Auth\OnboardAction`)| `status=InProgress` 遷移 1 行。B-B-06 と同ファイル |
| B-B-13 | auth | ✅ 適切 | Basic(FormRequest) | `OnboardingRequest` の `confirmed` ルール。確認欄の日本語表示名も定義済 |
| B-B-14 | chat | ✅ 適切 | ※ Service(`ChatUnreadCountService`)| 送信者除外条件が 3 メソッドに分散。再現には全削除が必要 |
| B-B-15 | auth | **差し替え** | Basic(route/Middleware) | 後述(旧案が観測不能 → role ガード漏れに変更)|
| B-B-16 | auth | ✅ 適切 | Basic(Middleware) | `EnsureActiveLearning` の単一不等式。status 追加時も安全側フォールバック |

### ⚠️→対応完了: B-B-03(公開停止資格の教材露出)

- **判定**: ⚠️ 修正必要 → **詳細化中に模範解答 PJ を修正(対応完了)**
- **背景**: 模範解答 PJ の受講生向け教材閲覧フロー(`Learning\Show{Part,Chapter,Section}Action`)に「親資格が公開停止(archived)なら弾く」絞り込みが **存在しなかった**(View Policy は Enrollment 状態のみ判定、各 Action は教材階層 [Part/Chapter/Section] の公開状態のみ判定)。一方 `CertificationPolicy`(student view)/ `QaThreadPolicy` / `QaReplyPolicy` は「公開中資格のみ」を判定する前例あり。learning 側だけ抜けていた(実装漏れ)。
- **ユーザー判断**(2026-05-24、AskUserQuestion): 「公開停止資格の教材は受講登録済みでも閲覧不可」を正とし模範解答に正解像を追加する方針で確定(製品仕様: archived = 受講登録の有無に関わらず教材閲覧不可)。
- **対応内容**: `ShowPartAction` / `ShowChapterAction` / `ShowSectionAction` に「親資格が公開中(`CertificationStatus::Published`)でなければ 404」ガードを追加(既存の `ContentStatus::Published` 404 判定と同じインライン guard パターン)。`tests/Feature/Http/Learning/BrowseControllerTest.php` に archived 404 ケース 3 件(`test_show_{part,chapter,section}_404_when_certification_archived`)+ ヘルパー `buildArchivedCertificationPart()` を追加し、`sail artisan test --filter=BrowseControllerTest` で全通過(13 passed)を確認。
- **Step 4**: 上記 3 Action のガード(3 箇所)を削除して提供 PJ を作る。

### 差し替え: B-B-15(旧「withdrawn ユーザーがログイン可能」)

- **判定**: 旧案は **観測不能** のため題材差し替え。
- **背景**: 退会処理(`UserWithdrawalService::withdraw`)は `status=withdrawn` + **SoftDelete** を必ずセットで行う(`WithdrawAction` / `RevokeInvitationAction` / `ExpireInvitationsAction` の全経路、Seeder も `deleted_at` セット済)。ログインクエリ(`User::where('email')->first()`)は SoftDelete グローバルスコープで退会ユーザーを除外するため、`AuthenticateUserUsing` の status チェックを削除しても、ブラウザ / Seeder データでは退会ユーザーは依然ログイン不可。status チェックは多層防御の位置づけで、削除しても観測可能なバグにならない(既存テストは `deleted_at=null` の退会ユーザーを作って検証しているが、これは本番状態と乖離)。
- **ユーザー判断**(2026-05-24、AskUserQuestion): 「B-B-15 を再考/差し替え」を選択。
- **差し替え後**: 観測可能な認証ミドルウェアのチェック漏れ = 「管理者専用ルート群(`routes/web.php` の `role:admin` group)の `role:admin` を `role:admin,coach` に書き換え → コーチが管理者専用エリア(ユーザー管理 / 招待管理 / プラン管理 / 資格マスタ管理 等)に混入」。Basic 範囲(route/`EnsureUserRole`)、コーチで 200 / 受講生は 403 維持、で観測可能。B-B-01(Policy、コーチの教材アクセスは正当な担当資格スコープ)と層が異なり対照的(Middleware ロール存在確認 vs Policy リソース固有認可)。
- **未確定の余地**: 旧案を活かす案(`deleted_at=null` の退会ユーザーを Seeder に追加してブラウザ観測可能化)も提示したが、B-B-04 の admin 一覧に副作用が出るため不採用。差し替え案で問題があればユーザー判断で再調整。

### 🕐 Phase D 横断課題: Story と Bug の対象重複(Step 4 引き算順序の調整) → ✅ 切り分け方針確定(2026-05-25)

詳細化中に「Story のロジック削除」と「Bug のバグ仕込み」が **同一 Feature/Controller を対象** とする組を検出。提供 PJ での共存方法を Phase D(全件詳細化完了後、2026-05-25)で確定。**実コード作業は Step 4 引き算工程で実施**。

| # | 課題 | 関連チケット | 切り分け方針(Step 4 で適用) |
|---|---|---|---|
| 3 | プラン管理 admin: S-B-03(Story、admin CRUD UI を受講生が実装 = ロジック削除して提供)と B-B-11(Bug、`Plan\IndexAction` の status フィルタにバグ仕込み)が同じプラン管理 admin を対象。S-B-03 がロジックを削除すると、B-B-11 のバグ対象コードが提供 PJ に存在しなくなる矛盾 | `S-B-03` / `B-B-11` | **Step 4 切り分け方針**: `Plan\IndexAction`(一覧)を **バグ込みで提供 PJ に残す**(B-B-11 用、status フィルタの enum 値コピペミス仕込み)+ `Plan\{Show,Store,Update,Destroy,Publish,Archive,Unarchive}Action.php` / `PlanController` の `show/create/store/edit/update/destroy` + `PlanStatusController` の `publish/archive/unarchive` + 関連 Blade(create/edit/show) は **S-B-03 範囲として受講生実装に回す**。`PlanController::index` は Action 呼出 1 行(`return $action(...)` 程度)で薄いため、Index Action 込みで残しても S-B-03 の Index 受講生実装を妨げない(受講生は提供された `PlanController::index` + `Plan\IndexAction` を読んでフィルタバグを発見しつつ、CRUD / 状態遷移 7 メソッドを自力実装する流れ)|
| 4 | settings-profile: S-B-07(Story、設定画面ロジックを受講生が実装)と B-B-08(Bug、`ProfileController::update` のリダイレクト誤り)が同じ settings-profile を対象 | `S-B-07` / `B-B-08` | **Step 4 切り分け方針**: `Settings\ProfileController::update` + `Profile\UpdateAction` を **バグ込みで提供 PJ に残す**(B-B-08 用、リダイレクト先 dashboard 誤り仕込み)+ `Settings\ProfileController::edit`(GET フォーム表示) / `Settings\{Avatar,Password}Controller` 全メソッド / `Avatar\{Store,Destroy}Action` / Fortify `UpdateUserPassword` 統合 / 関連 Blade(profile タブ / avatar アップロード / password タブ) は **S-B-07 範囲として受講生実装に回す**。`ProfileController::update` を残しつつも、受講生は edit / avatar / password の実装が必要なので学習量は十分に確保される + リダイレクト先のバグを「動作確認時に dashboard へ遷移したから」気づく流れになる |

> Step 4 引き算実装時、上記方針に従って模範解答 PJ から提供 PJ への変換を行う。B-B-11 のメタには依存 `S-B-03` を記録済(B-B-11 を先に動かすには S-B-03 の Index が動いている前提)。S-B-07 → B-B-08 も同様の依存関係(設定画面に動作確認で到達してバグを発見する流れ)で順序整合する。

---

## S-B-03 / plan-management-01: プラン管理 Admin マスタ UI(2026-05-25)

### レビュー対象

- `模範解答プロジェクト/app/Http/Controllers/{PlanController,PlanStatusController}.php`
- `模範解答プロジェクト/app/UseCases/Plan/*Action.php`(8 本: Index / Show / Store / Update / Destroy / Publish / Archive / Unarchive、加えて本チケット範囲外の ExtendCourseAction / GraduateUserAction)
- `模範解答プロジェクト/app/Models/Plan.php`
- `模範解答プロジェクト/app/Enums/PlanStatus.php`
- `模範解答プロジェクト/app/Policies/PlanPolicy.php`
- `模範解答プロジェクト/app/Http/Requests/Plan/{Store,Update,Index}Request.php`
- `模範解答プロジェクト/app/Exceptions/Plan/{PlanInvalidTransition,PlanNotDeletable,PlanNotPublished,UserNotInProgress}Exception.php`
- `模範解答プロジェクト/resources/views/plan/management/*.blade.php`(4 ファイル)
- `模範解答プロジェクト/database/migrations/2026_05_17_000000_create_plans_table.php`
- `模範解答プロジェクト/database/seeders/PlanSeeder.php`

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Controller 分離(CRUD / 状態遷移) | ✅ 適切 | `PlanController`(CRUD)+ `PlanStatusController`(publish / archive / unarchive)の二分割で Single Responsibility が明確。`backend-http.md`「namespace 方針」のフラット命名規約と整合 |
| Policy(admin 真偽判定のみ) | ✅ 適切 | `delete` メソッドも admin 真偽のみ、状態ベース + 参照ベースガード(下書き × 受講者なしのみ削除可)は Action 内で `PlanNotDeletableException` を throw。Policy(人ベース)/ Action(状態 / 参照ベース)の責務分離規約に準拠 |
| 削除制約(物理削除 + 下書き × 受講者なし) | ✅ 適切 | `DestroyAction` で 2 段ガード(`status !== Draft` → 409 / `users()->exists()` → 409)。SoftDelete 不採用は `backend-models.md`「マスタ系で Draft/Published/Archived の status 列を持つ Entity は SoftDelete 採用しない」規約準拠 |
| Seeder の状態網羅 + 受講者紐づけの多様化 | ✅ 適切 | published × 3(`1ヶ月`/`3ヶ月`/`6ヶ月`、回数バリエーション) + draft × 1 + archived × 1 で一覧フィルタ・状態遷移ボタン活性条件を実機確認可能。さらに受講生 × 期限進捗を 8 パターン(開始直後 / 中盤 / 期限直前)散らして dashboard / ユーザー詳細の表示確認にも流用 |
| View ディレクトリ命名(`plan/management/`) | ✅ 適切 | `frontend-blade.md` の Entity 単数 kebab-case + `management/` サブディレクトリ規約に準拠 |
| 詳細画面の受講者一覧 + N+1 回避(`withCount('users')`) | ✅ 適切 | `IndexAction` は `withCount('users')` + `ShowAction` は `load(['createdBy', 'updatedBy', 'users'])` で Eager Load 済 |
| **例外クラスのメッセージ責務(規約違反)** | ⚠️→**🕐 Phase D 一括処理** | 後述(MeetingPack 側と統一)|
| 詳細画面のプラン延長 / 期限満了履歴表示 | ⚪ 本チケット範囲外 | `ExtendCourseAction` / `GraduateUserAction` は提供 PJ 同梱で、受講生は別チケット(`B-B-11` 等)で利用側として触れる。本チケットでは詳細画面に受講者一覧のみ表示 |

### ⚠️→対応完了: 例外クラスのメッセージ責務(MeetingPack 側との不統一)

- **判定**: ⚠️ 規約違反 → **対応完了**(2026-05-25、Phase D で MeetingPack 側と統一)
- **背景**: 模範解答 PJ の `PlanInvalidTransitionException` / `PlanNotDeletableException` は `__construct(string $message = '...', ...)` 形式で、Action 側から個別メッセージを文字列で渡していた(`PublishAction` が「下書き(draft)状態のプランのみ公開できます。」、`ArchiveAction` が「公開中(published)のプランのみアーカイブできます。」、`UnarchiveAction` が「アーカイブ済みのプランのみアーカイブ解除できます。」、`DestroyAction` が「このプランは受講者が紐づいているため削除できません。」を渡す)。
- **規約**: `backend-exceptions.md`「メッセージ責務は例外クラスが所有する」では「Action / Service / Controller から例外コンストラクタに文字列メッセージを渡してはならない。メッセージ文言は例外クラス側の責務として完結させる」と定義。バリエーションが必要な場合は **static factory `forPublish()` / `forArchive()` / `forUnarchive()`** を例外クラス側で提供し、Action 側はファクトリで呼ぶだけにする。
- **対比**: 同型実装の MeetingPack 側(`MeetingPackInvalidTransitionException`)は **既に static factory パターンで規約準拠**(`forPublish` / `forArchive` / `forUnarchive` の 3 ファクトリ + `private __construct`)。Plan 側だけ規約違反が残存していた。
- **対応内容**(2026-05-25):
  - `app/Exceptions/Plan/PlanInvalidTransitionException.php` を MeetingPack 同型の `forPublish` / `forArchive` / `forUnarchive` static factory + `private __construct` + `final class` パターンに統一。メッセージも MeetingPack 文体に揃える(「下書きのプランのみ公開できます。」「公開中のプランのみアーカイブできます。」「アーカイブ済みのプランのみ下書きへ戻せます。」)
  - `app/Exceptions/Plan/PlanNotDeletableException.php` を「下書き状態違反 = `forStatusViolation()`」と「受講者紐づきあり = `forUsersAttached()`」の 2 ファクトリに分離(`private __construct` + `final class`)。メッセージは「下書き状態のプランのみ削除できます。先に下書きに戻すか、アーカイブを利用してください。」 / 「このプランは受講者が紐づいているため削除できません。」
  - `app/UseCases/Plan/{Publish,Archive,Unarchive,Destroy}Action.php` の throw 文 5 箇所を static factory 呼出(`throw PlanInvalidTransitionException::forPublish()` 等)に書き換え
  - テスト確認: `sail artisan test tests/Feature/UseCases/Plan tests/Feature/Http/Plan` で全 23 テスト pass(`PlanControllerTest::assertSessionHas('error')` はキー存在チェックのみで文言不問、メッセージ文字列の直接 assert はなしを確認済)
- **本チケット側**: 実装方針「アーキテクチャ判断 §2」で「メッセージ文字列は例外クラス側が所有する」を明示済み(規約準拠形を 100% 版の設計として記述)。模範解答 PJ も規約準拠形に揃った。

→ ✅ 適切(本チケットの 100% 版記述は規約準拠形で完結。模範解答 PJ の例外クラスも MeetingPack 側と統一済)

---

## S-B-04 / notification-01: 通知基盤(Laravel Notification、DB + Mail)(2026-05-25)

### レビュー対象

- `模範解答プロジェクト/app/Notifications/BaseNotification.php`(抽象基底)
- `模範解答プロジェクト/app/Notifications/{Chat,QaBoard,Mentoring}/*Notification.php`(本チケット範囲の 4 通知種別: ChatMessageReceived / QaReplyReceived / MeetingReserved / MeetingCanceled)
- `模範解答プロジェクト/app/Http/Controllers/NotificationController.php`(`index` / `markAsRead` / `markAllAsRead`)
- `模範解答プロジェクト/app/UseCases/Notification/{Index,MarkAsRead,MarkAllAsRead}Action.php`
- `模範解答プロジェクト/app/UseCases/Notification/Notify{ChatMessageReceived,QaReplyReceived,MeetingReserved,MeetingCanceled}Action.php`(発火ラッパー Action 4 本)
- `模範解答プロジェクト/app/Policies/NotificationPolicy.php`
- `模範解答プロジェクト/app/Http/Requests/Notification/IndexRequest.php`
- `模範解答プロジェクト/app/View/Composers/NotificationBadgeComposer.php`
- `模範解答プロジェクト/database/migrations/2026_06_04_000000_create_notifications_table.php`
- `模範解答プロジェクト/database/seeders/NotificationSeeder.php`
- `模範解答プロジェクト/resources/views/notifications/{index,_partials/notification-row}.blade.php`

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| `BaseNotification` 抽象基底 + ULID 事前確定 | ✅ 適切 | コンストラクタで `$this->id = (string) Str::ulid()` を確定し、DatabaseNotification の主キー(時系列ソート)と一致。サブクラスは ULID を意識せず `toDatabase` / `toMail` 実装に集中できる |
| `via()` の既定 3 チャネル(`database` + `mail` + `broadcast`) | ✅ 適切 | Broadcasting driver が `null`(Basic 段階)では Laravel が broadcast チャネルを no-op 扱いするため、振る舞い的に DB + Mail のみ。S-A-05 で Pusher driver 有効化したときに自動で 3 チャネル化される設計で、Basic / Advance の階段が綺麗 |
| chat 通知の Mail 抑制(`mailEnabled` フラグ) | ✅ 適切 | `ChatMessageReceivedNotification` の `via()` 内で `mailEnabled=false` のときに `mail` を配列から除外する責務分担(発火側 Action がフラグを決め、Notification クラスは反映のみ) |
| 受信者「受講中」スキップ | ✅ 適切 | 発火ラッパー Action 内で `$recipient->status !== UserStatus::InProgress` で個別ガード。基底側でなく発火側責務という配置は、ユーザー Model 依存を Notification クラスに持ち込まない設計判断として正 |
| chat 送信者除外 / Q&A 自己回答スキップ / 面談予約はコーチのみ / 面談キャンセルは相手方 | ✅ 適切 | 各ラッパー Action が個別判定を所有(`if ($recipient->id === $sender->id) continue;` 等)。発火条件のドメインロジックを 1 箇所に閉じ込めている |
| `NotificationBadgeComposer`(View Composer)| ✅ 適切 | 未認証チェック + `unreadNotifications()->count()` の 1 クエリで完結。99+ 表示は呼出側 Blade の責務とする責務分離 |
| `NotificationPolicy::view/update`(自分宛のみ) | ✅ 適切 | `notifiable_id === user->id` のみ判定、ロール無関係。Laravel 標準 DatabaseNotification への morph 認可として最小限 |
| 行クリック既読化 + 遷移(`data.link_route` を `Route::has()` で安全解決) | ✅ 適切 | ルート未登録時に通知一覧へフォールバックする防御策あり。受講生 / コーチ / admin 共通で同 Controller を使うため `data.link_route` の駆使は妥当 |
| `data` JSON のキャッシュ戦略(`sender_name` / `thread_title` 等) | ✅ 適切 | 通知発火時点でスナップショット化、一覧描画時の eager load を不要にする(NFR-notification-002 準拠)。発火後のドメイン Model 変更(送信者改名等)は通知行に反映されないが、通知ログとしては受信時点情報が正という考えと一致 |
| `notifications` Migration の ULID morph + 複合 INDEX | ✅ 適切 | `ulidMorphs('notifiable')` で ULID 対応(`morphs` のデフォルト BIGINT では Data truncated 事故)。`(notifiable_type, notifiable_id, read_at)` 複合 INDEX で自分宛 + 未読絞り込みクエリを最適化 |
| Seeder の種別網羅 + 既読 / 未読 半々 | ✅ 適切 | 固定 student / 固定 coach に全種別 1 件ずつ + デモユーザー × 数件で型網羅、`read_at` を null と過去日付で半々に振り未読バッジ / 未読タブの動作確認可。`Notification::send()` ではなく直接 INSERT で副作用(Queue / Mail)を抑制している実装上の工夫も妥当 |
| 発火フックの配置(`DB::afterCommit` 経由) | ✅ 適切 | 業務トランザクション内で発火しないことで「業務 UPDATE ロールバック + 通知だけ残る」事故を回避。Basic 受講生が Controller 内同期発火に簡略化しても振る舞いは満たせる |
| `MeetingReminderNotification` + `SendMeetingRemindersCommand` | ⚪ 本チケット範囲外 | 模範解答 PJ では本クラス + Schedule Command が存在するが、本チケット 100% 版では「やらないこと」に明示してスコープから外す(Schedule Command 自体が Basic 範囲を逸脱)。Phase 7 で mentoring Feature 拡張 / 別 Story として扱う候補 |
| `AnnouncementNotification` + `NotifyAnnouncementAction` | ⚪ 本チケット範囲外 | `S-B-09` で扱う、本チケットからはスコープアウト |
| `Api/IndexAction` / `Api/MarkAllAsReadAction` + `Api/V1/NotificationController` | ⚪ 本チケット範囲外 | `S-B-05`(認証なし API)+ `S-A-05`(Sanctum + JS フロント)で扱う |
| `BaseNotification::viaQueues()` の `mail => 'notifications'` キュー分離 | ⚪ 工夫として保持 | Queue Worker 運用 + キュー振り分けは本チケットで詳細扱わない(Basic 段階では Queue=`sync` で同期送信)。S-A-05 / 別 Queue チケットで実体化想定 |

→ ✅ 適切(本チケット範囲は規約準拠形で完結。範囲外要素は ⚪ で識別して他チケット責務に委ねる構成)

---

## S-B-05 / notification-02: 通知 JSON API(認証なし)(2026-05-25)

### レビュー対象

- `模範解答プロジェクト/app/Http/Controllers/Api/V1/NotificationController.php`
- `模範解答プロジェクト/app/UseCases/Notification/Api/{Index,MarkAllAsRead}Action.php`
- `模範解答プロジェクト/app/Http/Resources/Api/V1/NotificationResource.php`
- `模範解答プロジェクト/app/Http/Requests/Api/V1/Notification/IndexRequest.php`
- `模範解答プロジェクト/routes/api.php`(`v1` group)

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Web/API 分離(`App\Http\Controllers\NotificationController` vs `App\Http\Controllers\Api\V1\NotificationController`) | ✅ 適切 | `backend-http.md`「領域別 namespace」の Webhooks / Auth と同じ特殊カテゴリ扱い。Web 側はリダイレクト + flash、API 側は JSON 応答で責務が完全に分かれる |
| Resource クラスでの `data` JSON 平坦化 | ✅ 適切 | `data` 連想配列を `notification_type` / `title` / `message` / `link_route` / `link_params` の固定キーに整形、JS フロント側で扱いやすい安定スキーマを提供。`mixin DocBlock` で IDE 補完も効く |
| Api FormRequest(`IndexRequest`)による `tab` / `per_page` / `page` 検証 | ✅ 適切 | `per_page` の上限 100、`tab` の `in:all,unread` 制約で API レスポンス爆発を防ぐ。BookShelf Basic 公開 API と同型 |
| Web/API Action 共有(`MarkAsReadAction` は両者で再利用) | ✅ 適切 | べき等な単一既読化ロジックを 1 つの Action に集約。API 固有の `Api/IndexAction` / `Api/MarkAllAsReadAction` は per_page を変動させたり `updated` 件数を返したい等の API 固有要件があるため分離 |
| 単一既読化のべき等性(`if ($notification->read_at !== null) return;`) | ✅ 適切 | 再既読化で初回既読化時刻を上書きしない仕様。「最初にいつ読んだか」を永続化したい設計判断と整合 |
| ルート順序(`read-all` を `{notification}/read` より先に登録) | ✅ 適切 | `routes/api.php` の `v1` group 内で順序が正しく守られ、`read-all` パスが `{notification}` の動的セグメントに食われない |
| **模範解答 PJ の認証実装(Sanctum あり)と本チケット要件(認証なし)の差異** | ⚠️→**Step 4 で対応** | 模範解答 PJ は `routes/api.php` で `middleware('auth:sanctum')` 付き実装。本チケット 100% 版要件は「認証なし API」なので、Step 4 引き算で `->middleware('auth:sanctum')` を **取り外し** て提供 PJ にする。`S-A-05` で再度 Sanctum 認証を追加する 2 段階階段設計と整合。`user_id` クエリパラメータでのユーザー特定方式は本チケットで受講生に実装させる(模範解答 PJ は `$request->user()` から取得しているため、認証なし化と合わせて `?user_id={ulid}` への切替を Step 4 の作業範囲として記述する) |
| **`exists:users,id` バリデーション vs `findOrFail`** | ⚪ 受講生判断 | 模範解答 PJ は `$request->user()` 利用で本要件を満たさない(認証ありを前提とした実装)。本チケット 100% 版では `user_id` バリデーションを `exists:users,id`(422)か Controller 内 `findOrFail`(404)かを受講生判断とし、振る舞い目線で「不在ユーザーで取得を試みたらエラー」が伝われば良い扱いにする |
| MarkAllAsReadAction の `updated` 件数返却 | ✅ 適切 | `Eloquent` の `update()` は更新行数を返す Laravel 標準挙動を活用、JS 側で「N 件を既読化しました」表示の素材として利用できる |

→ ✅ 適切 + ⚠️ Step 4 で 1 点対応(`->middleware('auth:sanctum')` 取り外し + 認証ユーザー解決から `user_id` クエリパラメータ解決への切替)。本チケット 100% 版は規約準拠形で完結

---

## S-B-06 / enrollment-03: 個人目標(EnrollmentGoal)CRUD(2026-05-25)

### レビュー対象

- `模範解答プロジェクト/app/Models/EnrollmentGoal.php`
- `模範解答プロジェクト/app/Http/Controllers/EnrollmentGoalController.php`
- `模範解答プロジェクト/app/UseCases/EnrollmentGoal/{Store,Update,Destroy,MarkAchieved,UnmarkAchieved}Action.php`(5 本)
- `模範解答プロジェクト/app/Policies/EnrollmentGoalPolicy.php`
- `模範解答プロジェクト/app/Http/Requests/EnrollmentGoal/{Store,Update}Request.php`
- `模範解答プロジェクト/database/migrations/2026_05_17_000020_create_enrollment_goals_table.php`
- `模範解答プロジェクト/database/factories/EnrollmentGoalFactory.php`
- `模範解答プロジェクト/resources/views/enrollment-goal/edit.blade.php` + 親 `enrollment/_partials/` 配下の目標関連 partial

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Policy の責務委譲(`view` / `viewAny` を `EnrollmentPolicy::view` に委譲) | ✅ 適切 | 目標固有の閲覧スコープを持たず、親 Enrollment 認可に従う設計が DRY + 責務単一。`EnrollmentGoalPolicy` コンストラクタで `EnrollmentPolicy` を DI して `view($user, $goal->enrollment)` を呼ぶパターンは Laravel 公式の Policy 構成として標準 |
| Controller 分割(`index` / `create` / `show` を持たない) | ✅ 適切 | 目標一覧は親 `enrollments.show` の partial、新規作成フォームも親画面に inline、詳細単独画面は持たないという設計。1 個別目標の UI 動線が「編集 / 削除 / 達成マーク / 達成解除」の 4 アクションに絞られるため Controller method を 6 個に整理(`store` / `edit` / `update` / `destroy` / `markAchieved` / `unmarkAchieved`)している判断は妥当 |
| HTTP メソッド選択(達成マーク = `POST .../achieve`、達成解除 = `DELETE .../achieve`) | ✅ 適切 | 「達成扱いリソースを作る / 消す」メタファーで状態切替を REST 風に表現。受講生にメソッド選択の根拠を Q&A で学習させる良い教材になる |
| べき等性の担保(`update(['achieved_at' => now()])` を無条件実行) | ✅ 適切 | 「既達成なら何もしない」分岐を Action 内に書かず、Eloquent UPDATE のべき等性に委ねる単純な設計。再呼出で達成日時のみ書き換わる挙動も Q&A で明示 |
| 物理削除採用(SoftDelete 不採用) | ✅ 適切 | `backend-models.md`「進捗・履歴・累計集計テーブルは SoftDelete 採用しない」+ ON DELETE CASCADE で親 Enrollment 削除に追従する設計が筋 |
| Migration の `varchar(1000)` for description | ⚪ 軽微 | TEXT 型でもよいが、`max:1000` バリデーションで上限担保されているため `varchar(1000)` で実用上問題なし。Pro 生レベルとして「短い説明は varchar、長文は text」という運用判断が妥当 |
| FormRequest の `authorize()` で `EnrollmentGoal::class + $this->route('enrollment')` Policy ガード | ✅ 適切 | `StoreRequest::authorize()` で `->can('create', [EnrollmentGoal::class, $this->route('enrollment')])` を呼ぶことで、認可失敗時のレスポンスを Laravel 標準 403 に集約。Controller は `$this->authorize()` を別途呼ばずに済む |
| Controller の `markAchieved` / `unmarkAchieved` で `$this->authorize()` 呼出 | ✅ 適切 | Custom 業務操作は FormRequest を介さないため Controller 内で明示的に `$this->authorize('markAchieved', $goal)` を呼ぶパターン。`backend-http.md`「認可は Controller で実施」規約準拠 |

→ ✅ 適切(即時修正不要、Phase D 横断課題もなし)

---

## S-B-07 / settings-profile-01: 設定・プロフィール画面(2026-05-25)

### レビュー対象

- `模範解答プロジェクト/app/Http/Controllers/Settings/{Profile,Avatar,Password}Controller.php`(3 本)
- `模範解答プロジェクト/app/UseCases/Profile/UpdateAction.php`
- `模範解答プロジェクト/app/UseCases/Avatar/{Store,Destroy}Action.php`
- `模範解答プロジェクト/app/Actions/Fortify/UpdateUserPassword.php`(Fortify 公式パターン)
- `模範解答プロジェクト/app/Http/Requests/Profile/UpdateRequest.php`
- `模範解答プロジェクト/app/Http/Requests/Avatar/StoreRequest.php`
- `模範解答プロジェクト/app/Policies/UserPolicy.php`(`updateSelf` メソッド)
- `模範解答プロジェクト/resources/views/settings/profile.blade.php` + `_partials/tab-{profile,password,meeting}.blade.php`
- `模範解答プロジェクト/routes/web.php`(`settings.profile.*` group)

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Controller 分割(`Settings/` namespace 内 Profile / Avatar / Password) | ✅ 適切 | `backend-http.md`「Feature 別 namespace」許容ケースで、`/settings/*` 配下のサブ機能をグルーピング。フラット命名(`SettingsProfileController` 等)でも代替可だが、Settings サブ機能が複数 Controller に渡るため namespace 切りが読みやすい |
| `Profile\UpdateAction` の coach 専用 `meeting_url` silently drop | ✅ 適切 | `if ($user->role === UserRole::Coach && array_key_exists(...))` の 2 条件で「coach 以外は silently drop」を Action 内に閉じ込め。受講生 / 管理者の偽装送信を防御層で無効化、エラーにはしないパターンが Spec 通り |
| `meeting_url = ''` 時の NULL 保存 | ✅ 適切 | 「空文字をクリア動作として扱う」業界標準のフォーム挙動。バリデーションは `nullable / url` で空文字でも通過、Action 内で NULL に正規化 |
| Avatar 3 ステップ更新(新ファイル保存 → DB UPDATE → 旧ファイル削除) | ✅ 適切 | `DB::transaction` 内で 2 ステップ目を保護、3 ステップ目は best-effort で UPDATE 後に削除する設計が筋。新ファイル保存失敗時の例外伝播 / DB UPDATE 失敗時の新ファイル削除も spec 通り |
| Fortify Password Update 利用(`UpdateUserPassword`) | ✅ 適切 | Fortify 公式パターン Action(`Laravel\Fortify\Contracts\UpdatesUserPasswords`)を Controller 内で直接呼ぶ最小限の統合。FormRequest を新規作成せず、Fortify の `validateWithBag` に検証を委譲する設計が筋。`backend-usecases.md`「Fortify Action と UseCase Action の名前空間衝突」規約準拠 |
| `UserPolicy::updateSelf`(自己更新のみ true) | ✅ 適切 | 既存 `update`(管理者経由)と別メソッドで共存し、責務(admin 経由 vs 本人経由)を Policy 層で分離。`FormRequest::authorize()` から呼ぶ集約パターン |
| `EnsureActiveLearning` Middleware 不適用 | ✅ 適切 | `graduated` 受講生もアカウント保守機能は利用可能、`product.md` 方針と整合 |
| ProfileController::edit が `AvailabilityIndexAction` を eager load する設計 | ⚪ 本チケット範囲外 | 模範解答 PJ の `ProfileController::edit` は coach 用 `availabilities` を常時 eager load しているが、これは Advance スコープ(`?tab=meeting` の面談設定タブ)用。Step 4 引き算で本チケット範囲を絞る際、Profile タブ + Password タブのみに簡素化する案がある(Basic 受講生は coach の `availabilities` 取得ロジックを書かなくて済む) |
| 面談設定タブ(`?tab=meeting`)+ `tab-meeting.blade.php` | ⚪ 本チケット範囲外 | `S-A-01`(mentoring / Google Calendar 連携)で扱う。Step 4 引き算で「coach のタブ 3 種 → 2 種」「`tab-meeting.blade.php` を提供 PJ から除外 or `S-A-01` 範囲として残置」を Phase D で確定する |
| Google Calendar 連携 Controllers(`CoachGoogleCredentialController`)+ `/settings/google-calendar/*` ルート | ⚪ 本チケット範囲外 | 同上 `S-A-01` |
| **B-B-08 との Step 4 重複**(`ProfileController::update` のリダイレクト誤り) | 🕐 Phase D 課題 #4 | 既に `_review-log.md` Phase D 課題 #4 に記録済。本チケット 100% 版でロジック削除して提供 PJ にすると、B-B-08 のバグ仕込み対象が消える矛盾を Step 4 実装時に整合させる(例: profile 編集 / avatar / password のうち一部 [update リダイレクト] を提供して B-B-08 バグを仕込み、他は受講生実装に回す等の切り分け) |

→ ✅ 適切(本チケット 100% 版は規約準拠形で完結。範囲外要素は ⚪ で識別、B-B-08 重複は 🕐 Phase D 課題 #4 で整合確認)

---

## S-B-08 / mentoring-06: コーチ用 受講生メモ(EnrollmentNote)編集(2026-05-25)

### レビュー対象

- `模範解答プロジェクト/app/Models/EnrollmentNote.php`
- `模範解答プロジェクト/app/Http/Controllers/EnrollmentNoteController.php`
- `模範解答プロジェクト/app/UseCases/EnrollmentNote/{Store,Update,Destroy}Action.php`(3 本、達成マーク系はなし)
- `模範解答プロジェクト/app/Policies/EnrollmentNotePolicy.php`
- `模範解答プロジェクト/app/Http/Requests/EnrollmentNote/{Store,Update}Request.php`
- `模範解答プロジェクト/database/migrations/2026_05_17_000021_create_enrollment_notes_table.php`
- `模範解答プロジェクト/resources/views/enrollment-note/{edit,_list}.blade.php`

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Policy の二重判定(`canAccessEnrollmentForNotes` + `canModify` の private ヘルパー集約) | ✅ 適切 | 「担当コーチ判定 + 管理者越境 + 作成者本人判定」の組合せロジックをヘルパーに DRY 化。可読性と重複排除両立、`backend-policies.md` 規約準拠 |
| 管理者越境 + コーチ間越境拒否のロジック | ✅ 適切 | `update` / `delete` で「`admin true / coach && coach_user_id === user.id`」の判定が明確。他コーチが書いたメモへの介入を `403` で阻む業務設計が、運用要件(複数コーチ体制での個別管理)と整合 |
| 受講生 (`student`) の全メソッド拒否 | ✅ 適切 | `canAccessEnrollmentForNotes` の `if ($user->role !== Coach) return false` で student / その他は default false に。`backend-policies.md`「safe default」規約準拠 |
| `coach_user_id` カラム名の運用(管理者作成時も同カラム) | ⚪ 軽微 | カラム名は `coach_user_id` だが実体は「作成者の User ID」(管理者作成時も同カラム)。`author_user_id` にリネームする案もあるが、テーブル定義・Model リレーション・受講生 PR の Blade / Action / Migration の一貫性が取れていれば実用上問題なし(`backend-models.md` 規約違反ではない) |
| 編集時の `coach_user_id` 不変性 | ✅ 適切 | `UpdateAction::__invoke()` で `$note->update(['body' => $validated['body']])` のみ更新、作成者は変更しない。`backend-usecases.md`「Action の責務」規約準拠 |
| FormRequest の `authorize()` で Policy 統合 | ✅ 適切 | `StoreRequest::authorize()` で `->can('create', [EnrollmentNote::class, $this->route('enrollment')])` を呼ぶことで、認可失敗時のレスポンスを Laravel 標準 403 に集約。Controller 側で `$this->authorize()` を別途呼ばずに済む(`Update` も同様) |
| Controller の `edit` / `destroy` で `$this->authorize()` 呼出 | ✅ 適切 | FormRequest を介さない `edit`(GET フォーム表示)と `destroy`(DELETE)では Controller 内で明示的に `$this->authorize('update', $note)` / `delete` を呼ぶ。`backend-http.md`「認可は Controller で実施」規約準拠 |
| 物理削除採用(SoftDelete 不採用) | ✅ 適切 | `backend-models.md`「進捗・履歴・累計集計テーブルは SoftDelete 採用しない」+ ON DELETE CASCADE で親 Enrollment 削除に追従する設計が筋 |
| `coach_user_id` の `ON DELETE RESTRICT` | ✅ 適切 | 作成者 User の物理削除を阻む = SoftDelete でしか退会できない、`withTrashed()` で作成者名を解決可能。`backend-models.md` 規約と整合 |
| `Enrollment::notes()` リレーション(`hasMany`)の存在前提 | ⚠️→**Step 4 で確認** | 模範解答 PJ では `Enrollment` Model に `notes()` リレーションが定義されている前提。Step 4 引き算で本チケット範囲(`EnrollmentNote` 系のみ)を削除する際、`Enrollment::notes()` のリレーションメソッドも一緒に削除するか、Enrollment.php を提供 PJ で維持するかの方針を確認。`EnrollmentGoal`(`S-B-06`)の `goals()` リレーションと同様の Phase D 課題 |
| `Enrollment::notes()` リレーション(`hasMany`) | ⚪ 関連エンティティへの軽微 | 既存 PJ で `Enrollment` Model に `notes()` リレーションが定義されている前提。本チケットで `EnrollmentNote` Model を新規追加する際、`Enrollment` Model に `hasMany(EnrollmentNote::class)` を生やす必要がある(受講生が忘れがちな箇所、Q&A 補足の余地あり) |

→ ✅ 適切(本チケット 100% 版は規約準拠形で完結、即時修正不要、Phase D 横断課題もなし)

---

## S-B-09 / notification-05: admin お知らせ配信機能(2026-05-25)

### レビュー対象

- `模範解答プロジェクト/app/Models/Announcement.php`
- `模範解答プロジェクト/app/Enums/AnnouncementTargetType.php`
- `模範解答プロジェクト/app/Http/Controllers/AnnouncementController.php`(`index` / `create` / `store` / `show` の 4 メソッド)
- `模範解答プロジェクト/app/UseCases/Announcement/{Index,Show,Store}Action.php`(3 本、配信は不可逆 = Update / Destroy なし)
- `模範解答プロジェクト/app/UseCases/Notification/NotifyAnnouncementAction.php`(発火ラッパー Action)
- `模範解答プロジェクト/app/Notifications/Announcement/AnnouncementNotification.php`(`BaseNotification` 継承)
- `模範解答プロジェクト/app/Policies/AnnouncementPolicy.php`(全 admin のみ true)
- `模範解答プロジェクト/app/Http/Requests/Announcement/StoreRequest.php`
- `模範解答プロジェクト/app/Exceptions/Notification/{AnnouncementInvalidTargetException, AnnouncementTargetNotFoundException}.php`
- `模範解答プロジェクト/database/migrations/2026_06_04_000001_create_admin_announcements_table.php`(+ `2026_06_20_000000_rename_admin_announcements_to_announcements.php` の rename Migration)
- `模範解答プロジェクト/resources/views/announcement/management/{index,create,show}.blade.php` + `_partials/target-fields.blade.php`

### 結論

| 観点 | 結論 | 詳細・対応 |
|---|---|---|
| Controller の 4 メソッド限定(`index` / `create` / `store` / `show`、`Route::resource::only`)| ✅ 適切 | 配信の不可逆性を Controller / Route レベルで強制。`edit` / `update` / `destroy` を持たず、UI 上にも編集 / 削除ボタンを配置しない設計が筋 |
| `target_type` Enum + `match` 式での受講生集合解決 | ✅ 適切 | `AllStudents` / `Certification` / `User` の 3 種類を `match` で切り替え、各クエリが個別最適化されている(全受講生 = role + status のみ / 資格指定 = `whereHas('enrollments', ...)` / ユーザー指定 = `where('id', ...)`)。`backend-models.md` Enum 規約準拠 |
| 受講中 status フィルタの集合解決時組込 | ✅ 適切 | `User::query()->where('status', UserStatus::InProgress)` を全 3 種類のクエリに組込、退会済 / 修了済 / 招待中 を事前除外。`NotifyAnnouncementAction` 側でも `status` 再確認の二重防御 |
| `DB::transaction` + `DB::afterCommit` パターン | ✅ 適切 | `Announcement` INSERT + `dispatched_count` / `dispatched_at` UPDATE を原子化、通知発火は `afterCommit` で実行することで ROLLBACK 時のメール副作用残りを回避。`S-B-04` と同じ設計判断 |
| 整合性チェックの二重防御(FormRequest + Action) | ✅ 適切 | FormRequest の `required_if` / `prohibited_unless` / `exists` で大半を 422 で弾き、Action 内で `AnnouncementInvalidTargetException` / `AnnouncementTargetNotFoundException` を 422 / 404 で再検査。境界条件の網羅性が高い |
| `NotifyAnnouncementAction` の戻り値 bool(配信成功 = true)| ✅ 適切 | 呼出側で配信件数カウントに利用、`return false` でスキップしたケースを呼出側で集計除外できる。`backend-usecases.md` Action 戻り値設計として妥当 |
| `AnnouncementNotification` の `toDatabase()` で `link_route='notifications.index'` | ✅ 適切 | お知らせ単独画面を持たず、通知一覧画面で確認する設計判断と整合。`S-B-04` の `data.link_route` 共通キー仕様準拠 |
| Migration の rename(`admin_announcements` → `announcements`) | ⚪ 軽微 | 本チケットでは初回 Migration から `announcements` テーブル名で実装する 100% 版を記述。模範解答 PJ の rename 履歴は内部経緯(2026-06-04 で `admin_announcements` 作成 → 2026-06-20 で rename)、本チケットの受講生実装には影響しない |
| `target_certification_id` / `target_user_id` の `ON DELETE SET NULL` | ✅ 適切 | 配信履歴を残しつつ、参照先資格 / ユーザーが削除された場合は NULL に切り替えて履歴整合性を保つ。`withTrashed()` で削除済 createdBy / targetUser を解決可能 |
| `Announcement` の SoftDelete 不採用 + 配信不可逆 | ✅ 適切 | `backend-models.md`「履歴系テーブルは SoftDelete 採用しない」+ 配信不可逆性を仕様として確定 |
| Seeder の `target_type` 3 種網羅 + 通知行の直接 INSERT | ✅ 適切 | `NotificationSeeder` 側で `type=AnnouncementNotification` として直接 INSERT し、Mail / Queue 副作用を抑制する設計が妥当 |

→ ✅ 適切(本チケット 100% 版は規約準拠形で完結、即時修正不要、Phase D 横断課題もなし)

---

## B-A-01〜B-A-03: Bug Advance 3 件 一括詳細化(2026-05-25)

### レビュー対象

| ID | Feature | レビュー範囲 |
|---|---|---|
| B-A-01 | mentoring(連携: meeting-quota) | `app/UseCases/MeetingQuota/ConsumeQuotaAction.php` / `app/UseCases/Meeting/StoreAction.php` / `app/Services/MeetingQuotaService.php` / `app/Models/MeetingQuotaTransaction.php` / `database/migrations/2026_05_17_000012_create_meeting_quota_transactions_table.php` / `app/Exceptions/MeetingQuota/InsufficientMeetingQuotaException.php` / 既存テスト `tests/Feature/UseCases/MeetingQuota/ConsumeQuotaActionTest.php` |
| B-A-02 | mock-exam | `app/UseCases/MockExamSession/GradeAction.php` / `app/UseCases/MockExamSession/SubmitAction.php` / `app/Http/Controllers/MockExamSessionController.php` / `app/Models/MockExamSession.php` / `app/Enums/MockExamSessionStatus.php` / `database/migrations/2026_05_17_000023_create_mock_exam_tables.php` / `app/Services/WeaknessAnalysisService.php`(波及確認) / 既存テスト `tests/Feature/UseCases/MockExamSession/GradeActionTest.php` |
| B-A-03 | enrollment(連携: mock-exam) | `app/Services/TermJudgementService.php` / `app/Enums/TermType.php` / `app/Models/Enrollment.php` / `app/UseCases/MockExamSession/{Start,Submit,Destroy}Action.php` / 既存テスト `tests/Unit/Services/TermJudgementServiceTest.php` |

### 結論サマリ

| ID | Feature | レビュー | バグ箇所のレイヤー | 備考 |
|---|---|---|---|---|
| B-A-01 | mentoring / meeting-quota | ✅ 適切 | Action(`MeetingQuota\ConsumeQuotaAction`)| `lockForUpdate()` を `DB::transaction` 冒頭に置き、残数集計 SELECT と消費 INSERT を直列化する設計。Step 4 はこのロック行 1 行を削除して TOCTOU を露出する。Advance 範囲のため Basic 例外注記は不要 |
| B-A-02 | mock-exam | ✅ 適切 | Action(`MockExamSession\GradeAction`)| 採点ロジックは `round($totalCorrect / $totalQuestions * 100, 2)` で 0〜100 スケール格納、合格判定 `>= passing_score_snapshot` で比較。Step 4 は `* 100` を削除して 0〜1 スケールに歪める。`SubmitAction` 内 `DB::transaction()` + `lockForUpdate()` で二重提出は別防御。Advance 範囲のため Basic 例外注記は不要 |
| B-A-03 | enrollment | ✅ 適切 | Service(`TermJudgementService`)| 判定対象状態を `whereIn('status', ['in_progress', 'submitted', 'graded'])` で明示列挙し `exists()` 判定する設計。Step 4 はこの配列に `'canceled'` を追加して、キャンセル後も実践タームを保持し続けるバグを仕込む。Advance 範囲のため Basic 例外注記は不要 |

### 評価ポイント

- **3 件とも Advance 範囲(Action / Service 修正必須)** で、構造上の Basic 例外注記なし
- B-A-01 は **並行性** サブカテゴリ唯一の Bug。動作確認に並行 HTTP リクエスト or 並行性テストが必要(通常のブラウザ操作では再現困難)。受け入れ条件・実装方針内で並行性テストの再現シナリオを明示
- B-A-02 は **計算系**。受講生が体感しやすい題材(全問正解でも不合格)で、影響範囲が `WeaknessAnalysisService` の弱点ヒートマップ / 合格可能性スコアまで波及する点も Q&A で明示
- B-A-03 は **状態集合の境界判定**。`canceled` / `not_started` を判定対象から除外する根拠を教習所メタファーで補強し、Q&A で `graded` を含める理由・`not_started` / `canceled` を含めない理由の境界整理を学習ポイントとして提示

→ ✅ 適切(即時修正不要、Phase D 横断課題もなし、3 件とも模範解答 PJ の現状実装をそのまま採用)

---

## T-B-01 / T-B-02 / T-B-03: Task Basic 3 件 一括詳細化(2026-05-25)

### レビュー対象

各 Task に対応する模範解答 PJ の最適化済み実装(Action / Schedule Command / View / Test)を並列調査(3 グループ)。Step 4 巻き戻し対象 / Before-After 計測値 / テスト方針 / 模範解答 PJ の懐疑的レビューを取得。

| ID | Feature | レビュー対象ファイル群 |
|---|---|---|
| T-B-01 | user-management | `app/UseCases/User/IndexAction.php` / `app/Http/Controllers/UserController.php` / `app/Models/User.php`(`plan` リレーション)/ `resources/views/user/management/index.blade.php` / 既存テスト `tests/Feature/Http/User/IndexTest.php` + `tests/Feature/Http/Dashboard/DashboardQueryCountTest.php`(クエリ数 assert の参考実装) |
| T-B-02 | mentoring / dashboard | `app/UseCases/Dashboard/FetchCoachDashboardAction.php` / `app/Http/Controllers/DashboardController.php` / `app/Models/Enrollment.php`(`user` / `certification` / `learningSessions` リレーション)/ `resources/views/dashboard/coach.blade.php` + `_partials/coach/assigned-students-list.blade.php` / 既存テスト `tests/Feature/UseCases/Dashboard/FetchCoachDashboardActionTest.php` + `DashboardQueryCountTest.php` |
| T-B-03 | 横断(Schedule Command 群)| 対象: `app/Console/Commands/GraduateExpiredUsersCommand.php` / `FailExpiredEnrollmentsCommand.php` / `Mentoring/AutoCompleteMeetingsCommand.php`(3 件)。対象外で残す箇所として `Notification/SendMeetingRemindersCommand.php` / `ChatMemberSyncService::syncForCertification()`(既に `chunkById` 実装で振る舞い不変)/ `ExpireInvitationsAction`(全件 1 トランザクション原子性が業務要件)/ `SessionCloseService::closeStaleSessions()`(`lockForUpdate` で対象一括ロック必須)を確認 |

### 結論サマリ

| ID | Feature | レビュー | 改善対象のレイヤー | 備考 |
|---|---|---|---|---|
| T-B-01 | user-management | ✅ 適切 | Action(`User\IndexAction`)| `->with('plan')` 1 本で過不足なし。`plan_expires_at` は users テーブル直属カラム(非正規化)で追加クエリ不要。`withCount` 等の集計表示は一覧画面になし。20 件 / ページ |
| T-B-02 | dashboard(コーチ視点)| ✅ 適切 | Action(`Dashboard\FetchCoachDashboardAction`)| BelongsTo 2 本(`with(['user', 'certification'])`)+ HasMany の最大値集約 1 本(`withMax('learningSessions as last_activity_at', 'started_at')`)の複合パターン。担当受講生はページネーションなし(`get()` 全件、コーチ 1 名あたり通常少数のため) |
| T-B-03 | 横断(Schedule Command)| ✅ 適切(🕐 1 点)| Schedule Command 群 | 模範解答 PJ で chunk 系は `chunkById()` のみ採用、`chunk()` / `cursor()` の用例は **存在しない**。チケットタイトルに 3 種類挙がるが、概念教育(Q&A・採用技術判断理由)で補完する判断。本チケット成立には影響なし |

### 🕐→✅ Phase D 横断課題: T-B-03 の `cursor()` 用例の不在 → 概念教育のままで確定(2026-05-25)

- **背景**: チケットタイトルは「`chunk()` / `chunkById()` / `cursor()` でメモリ最適化」だが、`grep -rn "->chunk(\|->chunkById(\|->cursor(\|->lazy(" app/` の結果、模範解答 PJ の実装では `chunkById()` 用例のみ存在(5 箇所)。`chunk()` / `cursor()` / `lazy()` の用例は 0 件。
- **本チケットでの扱い**: 受け入れ条件は対象 3 件で `chunkById()` を採用する形(`chunk()` ではダメで `chunkById()` 必須となる WHERE 列 = UPDATE 列パターンを教材化)。`chunk()` / `cursor()` は採用技術判断理由 / Q&A で **使い分けの判断軸** を概念教育する形に整理。これにより本チケットは成立する。
- **Phase D 判断結果**(2026-05-25): **概念教育のままで確定**。理由 = (1) 本模擬案件のスコープに `cursor()` 用例を要する読み専用ストリーミング処理(レポート出力 / CSV エクスポート 等)が存在しない(40 チケット確定済)/ (2) `cursor()` 用例を追加するには別 Story チケット相当の新規機能追加が必要で、スコープを膨らませる必要がない / (3) 教材的価値は採用技術判断 + Q&A の概念教育で十分達成可能(本チケット実装方針 §採用技術と判断理由 §`chunk` / `chunkById` / `cursor` の使い分けの一般則 で網羅済)/ (4) 模範解答 PJ への用例追加はチケット成立に影響しない → 現状の T-B-03 詳細化(`chunkById()` 必須教材 + `cursor()` 概念教育)で完結とする。

### 評価ポイント

- **3 件とも N+1 / メモリ最適化系のパフォーマンス Task** で、Step 4 は模範解答 PJ から `->with(...)` / `->withMax(...)` / `->chunkById(...)` をピンポイントで削除して提供 PJ を作る方式。実装の理想形と一致する Step 4 仕込み箇所が明確
- **T-B-01 と T-B-02 で別アプローチ**: T-B-01 = 単純 Eager Loading(BelongsTo 1 本)、T-B-02 = 複合 Eager Loading(BelongsTo 2 本 + HasMany の MAX 集約)。受講生が「N+1 解消の引き出し」を 2 段階で学ぶ構造。READMEの「Admin ユーザー一覧 T-B-01 とは別 Feature / 別視点で被らない」注記と整合
- **T-B-03 の `chunkById()` 必須教材**: 対象 3 件とも `foreach` 内で WHERE 列(status)を更新するため、`chunk()` ではオフセットずれで取りこぼしが発生する。模範解答 PJ のコメントで根拠が明示されているが、Step 4 では当該コメントごと削除し受講生に自力で発見させる流れ
- **対象外として残す箇所の明示**: T-B-03 で `ExpireInvitationsAction`(全件 1 トランザクション原子性)/ `SessionCloseService::closeStaleSessions()`(`lockForUpdate` で一括ロック必須)を「やらないこと」に明記。受講生がこれらを `chunkById()` 化しないよう注意喚起。「分割処理にすべき」と「`->get()` のまま残すべき」の区別が判断軸として学習対象になる

→ T-B-01 / T-B-02 = ✅ 適切(即時修正不要、Phase D 横断課題なし)、T-B-03 = ✅ 適切(本チケット成立には影響なし、`cursor()` 用例の追加可否は 🕐 Phase D で再判定)

---

## T-A-01〜T-A-04: Task Advance 4 件 一括詳細化(2026-05-25)

### レビュー対象

mentoring(3 件)+ 横断 1 件の Task Advance 4 件に対応する模範解答 PJ 実装(`MeetingController` / `MeetingAvailabilityService` / `Meeting\*Action` / `GoogleCalendarService` / `GoogleOAuthService` / `GeminiLlmRepository` / `StripeWebhookController` および関連テスト群)を並列調査。リファクタ前後の状態 / 計測指標 / Mockery & `Http::fake` の使い分け / Step 4 巻き戻し方を確定。

| ID | Feature | レビュー範囲 |
|---|---|---|
| T-A-01 | mentoring | `app/Services/MeetingAvailabilityService.php`(`slotsForCertification`)/ `app/UseCases/Meeting/FetchAvailabilityAction.php` / `app/Http/Controllers/MeetingController.php::fetchAvailability` / `app/Models/{User,Certification,CoachAvailability,CoachGoogleCredential,Meeting}.php`(リレーション + Eager Loading 対象)/ 既存テスト `tests/Unit/Services/MeetingAvailabilityServiceTest.php`(クエリ数 assert の参考実装) |
| T-A-02 | mentoring | `app/Http/Controllers/MeetingController.php`(`store` / `cancel` / `upsertMemo` の 3 method)/ `app/UseCases/Meeting/{Store,Cancel,UpsertMemo}Action.php` / `app/Exceptions/Mentoring/{MeetingNoAvailableCoachException,MeetingStatusTransitionException,MeetingAlreadyStartedException,MeetingOutOfAvailabilityException}.php` / `app/Exceptions/MeetingQuota/InsufficientMeetingQuotaException.php` / 既存テスト `tests/Feature/UseCases/Meeting/{Store,Cancel,UpsertMemo}ActionTest.php` |
| T-A-03 | mentoring | `app/Services/Google/{GoogleCalendarService,GoogleOAuthService}.php` / `app/UseCases/CoachGoogleCredential/{FetchAuthUrl,Store,Destroy}Action.php` / `app/Http/Controllers/Settings/CoachGoogleCredentialController.php` / `app/Services/MeetingAvailabilityService.php`(`freebusy` 呼出) / `app/UseCases/Meeting/{Store,Cancel}Action.php`(`insertEvent` / `deleteEvent` 呼出)/ `config/services.php`(google scopes) |
| T-A-04 | 横断 | `tests/Unit/Services/MeetingAvailabilityServiceTest.php`(Mockery 既存) / `tests/Feature/UseCases/Meeting/StoreActionTest.php`(Mockery 既存) / `tests/Feature/UseCases/CoachGoogleCredential/StoreActionTest.php`(Mockery 既存) / `tests/Feature/Http/Settings/CoachGoogleCredentialControllerTest.php`(Mockery 既存) / `tests/Unit/Repositories/GeminiLlmRepositoryTest.php`(`Http::fake` 既存) / `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php`(HMAC ヘルパー既存) |

### 結論サマリ

| ID | Feature | レビュー | 対象レイヤー | 備考 |
|---|---|---|---|---|
| T-A-01 | mentoring | ✅ 適切 | Service(`MeetingAvailabilityService::slotsForCertification`) | `with('googleCredential')` Eager Load + `whereIn('coach_id', $coachIds)` 一括取得 + GCal 連携済コーチのみ `freebusy` 発行で **DB 4 本固定 + GCal 連携済コーチ数のみ API 発行** に最適化済。Step 4 は per-coach for-loop に巻き戻して 1+3N クエリ + 未連携コーチへの API 空打ち を露出。`MeetingAvailabilityServiceTest` 既存ケースが pass する範囲で巻き戻し可能 |
| T-A-02 | mentoring | ✅ 適切 | Controller / Action(`MeetingController` ↔ `Meeting\StoreAction/CancelAction/UpsertMemoAction`) | 状態変更系 3 method のみ対象(`index/show/fetchAvailability/indexAsCoach` の取得系は副作用なしで Controller 1-3 行のため対象外、`AutoCompleteMeetingAction` は Schedule Command 経路で Controller リファクタの対象外)。`DB::transaction` + `DB::afterCommit` の境界 + 具象例外 throw の責務分離が `backend-usecases.md` 規約準拠 |
| T-A-03 | mentoring | ✅ 適切 | Service(`app/Services/Google/GoogleCalendarService` / `GoogleOAuthService`) | 認可フロー Service(stateless、共通クライアント生成 + OAuth 認可 URL / トークン交換 / 取消) + Calendar 操作 Service(`CoachGoogleCredential` 引数受け + `freebusy` / `insertEvent` / `deleteEvent` + トークンリフレッシュ内包)の 2 分割が責務として明確。`Service\Google\` namespace 配置・Interface 不採用・`final` 不採用・`Http::fake` 非採用 の 4 判断が `backend-services.md` 規約と整合(`google/apiclient` ライブラリが独自 HTTP クライアントを構築するため `Http::fake` は効かず Mockery で `final` を外す判断が必須) |
| T-A-04 | 横断 (mentoring + ai-chat + meeting-quota) | ✅ 適切 | テスト全般(`tests/Unit/Services/Google/` + `tests/Unit/Repositories/GeminiLlmRepositoryTest` + `tests/Feature/Http/Webhooks/StripeWebhookControllerTest` + 利用側 Mockery テスト) | 外部 API ごとのモック手法の使い分け(Service = Mockery / Repository = `Http::fake` / Webhook = HMAC ヘルパー)が `backend-services.md` + `backend-repositories.md` の規約をそのまま体現。`GeminiLlmRepositoryTest` は 5 ケース(正常系 / HTTP 500 / 空コンテンツ / 503 リトライ → 200 / payload 検証)で網羅、`StripeWebhookControllerTest` は `private function sign()` ヘルパーで HMAC-SHA256 を DRY 化、`MeetingAvailabilityServiceTest` / `Meeting\StoreActionTest` で `$this->mock(GoogleCalendarService::class)` の Service レベルモック化が確立済 |

### 依存関係メモ

| チケット | 依存 | 補足 |
|---|---|---|
| T-A-01 | `S-A-01`(Google Calendar 連携) | 連携済コーチの `freebusy` API 呼出が `coach_google_credentials` テーブル + `GoogleCalendarService::freebusy` の存在を前提とするため |
| T-A-02 | `S-A-01` | S-A-01 で `MeetingController::store` 等が GCal 連携を含めてさらに肥大化した状態が Step 4 巻き戻しの対象 |
| T-A-03 | `S-A-01` / `T-A-02` | T-A-02 後に Action 内 + Controller / Service 内に散在する Google API ライブラリ参照を Service へ集約する流れ |
| T-A-04 | `S-A-01` / `S-A-02` / `S-A-03` / `T-A-03` | T-A-03 完了で Service が分離された後、その Service と Repository / Webhook に対するモックテストを追加する流れ |

### 評価ポイント

- **4 件すべて Advance 範囲(Service / Action / テスト戦略 が中核)** で、構造上の Basic 例外注記なし
- **T-A-01 と T-B-01 / T-B-02 で別アプローチ**: T-B-01/T-B-02 は単純 BelongsTo Eager Loading が中心、T-A-01 は **多テーブル横断 + 外部 API 呼出境界** の複合最適化(`with('googleCredential')` + `whereIn` 2 本 + 連携済コーチのみ `freebusy`)。N+1 解消の応用パターンとして配置
- **T-A-02 のスコープ絞り込み**: 模範解答 PJ では取得系 Action(`Index/Show/FetchAvailability/IndexAsCoach`)も分離済だが、本リファクタチケットでは **状態変更系 3 method** に絞る判断。「Controller 内に if/計算が増えたら Action に移す」規約 + 「単純取得系は Controller 内 1-3 行で済む」現実の両立
- **T-A-03 の Service 2 分割の必然性**: OAuth フロー(stateless)と Calendar 操作(`CoachGoogleCredential` 状態を持つ)を 1 クラスに統合すると、トークン管理が利用側に漏れる + テストが書きづらくなる。`backend-services.md` の責務分離規約と整合
- **T-A-04 のモック手法の使い分け**: 3 系統(Google API ライブラリ依存 = Mockery / `Http` Facade 依存 = `Http::fake` / 受信側 Webhook = HMAC ヘルパー)が学習のメイン題材。「全部 Mockery で統一」「全部 `Http::fake` で統一」が NG であることを実コードで体感させる構造
- **`Http::preventStrayRequests()` + `#[Group('external')]` の組み合わせ**: 外部 API 実呼出 0 回 を CI で保証する 2 段構え。`#[Group('external')]` で `--exclude-group external` の subset 実行も可能にする実務パターン

→ ✅ 4 件すべて適切(即時修正不要、Phase D 横断課題もなし)。

### S-A-01〜S-A-03 詳細化時に再確認すべきこと

T-A-01〜T-A-04 は `S-A-01`(Google Calendar 連携) / `S-A-02`(Gemini AI チャット) / `S-A-03`(Stripe 連携) の 3 Story が **同じ Feature の完成版を実装** することを前提とする。Story 側詳細化時に以下を整合確認:

- S-A-01 が `coach_google_credentials` テーブル + `app/Services/Google/{GoogleCalendarService,GoogleOAuthService}.php` + `Meeting\StoreAction` 内 `insertEvent` 呼出 + `Meeting\CancelAction` 内 `deleteEvent` 呼出 を含むこと(T-A-01 / T-A-02 / T-A-03 の対象コードが提供 PJ 段階で揃っている前提)
- S-A-02 が `app/Repositories/GeminiLlmRepository.php` + Repository に対する `Http::fake` テストの「正常系のみ削減」(T-A-04 対象)
- S-A-03 が `app/Http/Controllers/Webhooks/StripeWebhookController.php` + 既存テスト(`StripeWebhookControllerTest`)の「HMAC 署名ヘルパー / 冪等性テスト」分の削減(T-A-04 対象)

---

## S-A-01〜S-A-05: Story Advance 5 件 一括詳細化(2026-05-25)

### レビュー対象

Story Advance 5 件分の模範解答 PJ 実装(`mentoring` Google Calendar 連携 / `ai-chat` Gemini チャットボット / `meeting-quota` Stripe 連携 / `certification-management` PDF 出力 / `notification` Sanctum + JS フロント)を並列調査。基盤 Migration / Model / Action / Service / Repository / Controller / Policy / Blade / JS / `routes/api.php` / `config/*.php` を網羅し、Step 4 引き算範囲 + 依存チケット関係 + Advance 範囲(Action / Service / Repository / 外部 API / Sanctum 等)の整合を確定。

| ID | Feature | レビュー範囲 |
|---|---|---|
| S-A-01 | mentoring | `app/Models/CoachGoogleCredential.php` / `app/Services/Google/{GoogleCalendarService,GoogleOAuthService}.php` / `app/UseCases/CoachGoogleCredential/{FetchAuthUrl,Store,Destroy}Action.php` / `app/UseCases/Meeting/{Store,Cancel}Action.php`(GCal 組込み)/ `app/Services/MeetingAvailabilityService.php`(freebusy 統合) / `app/Http/Controllers/Settings/CoachGoogleCredentialController.php` / `app/Exceptions/Mentoring/GoogleOAuthException.php` / `database/migrations/*coach_google_credentials*` + `*add_google_event_id*` / `config/services.php` google scopes / `composer.json` `google/apiclient` |
| S-A-02 | ai-chat | `app/Models/{AiChatConversation,AiChatMessage}.php` / `app/Enums/{AiChatMessageRole,AiChatMessageStatus}.php` / `app/Repositories/{Contracts/LlmRepositoryInterface,GeminiLlmRepository}.php` / `app/Services/{AiChatPromptBuilderService,LlmChatResponse}.php` / `app/Http/Controllers/{AiChatConversationController,AiChatMessageController}.php` / `app/UseCases/AiChat/{Show,Store,Update,Destroy,GenerateTitle}Action.php` + `AiChatMessage/{Store,Retry}Action.php` / `app/Policies/AiChatConversationPolicy.php` / `app/Exceptions/AiChat/*.php`(6 ファイル)/ `config/ai-chat.php` / `database/migrations/*ai_chat*` / `database/seeders/AiChatSeeder.php` / `resources/views/ai-chat/*.blade.php` + `resources/views/components/ai-chat/floating-widget.blade.php` / `resources/js/ai-chat/*.js` / `routes/web.php` の ai-chat group |
| S-A-03 | meeting-quota | `app/Models/Payment.php` / `app/Enums/PaymentStatus.php` / `app/Http/Controllers/{MeetingQuotaCheckoutController,Webhooks/StripeWebhookController}.php` / `app/UseCases/MeetingQuota/{CreateCheckoutSession,PurchaseQuota}Action.php` + `StripeWebhook/HandleAction.php` / `app/Http/Middleware/VerifyStripeSignature.php` / `app/Http/Requests/MeetingQuota/CheckoutRequest.php` / `app/Policies/MeetingQuotaPolicy.php` / `app/Exceptions/MeetingQuota/{StripeWebhookSignatureInvalid,MeetingPackNotPublished,UserNotInProgress}Exception.php` / `database/migrations/*payments*` / `config/services.php` stripe / `composer.json` `stripe/stripe-php` / `routes/web.php` の `/meeting-quota/checkout` + `/webhooks/stripe` |
| S-A-04 | certification-management | `app/Models/Certificate.php`(提供 PJ で既存)/ `app/Services/{CertificatePdfService,CertificateSerialNumberService}.php` / `app/UseCases/Certificate/{Issue,Download}Action.php` / `app/Http/Controllers/CertificateController.php` / `app/Policies/CertificatePolicy.php` / `app/Exceptions/Certification/{CertificateAlreadyIssued,CertificateGenerationFailed,CertificatePdfNotFound,EnrollmentNotPassed}Exception.php` / `resources/views/certificates/pdf.blade.php` / `database/migrations/*certificates*` / `composer.json` `mpdf/mpdf` / `routes/web.php` の `/certificates/{certificate}/download` |
| S-A-05 | notification | `app/Http/Controllers/Api/V1/NotificationController.php`(`auth:sanctum` で保護)/ `app/UseCases/Notification/Api/{Index,MarkAllAsRead}Action.php` / `app/Http/Resources/Api/V1/NotificationResource.php` / `app/Http/Requests/Api/V1/Notification/IndexRequest.php` / `routes/api.php`(`v1` group + `auth:sanctum`)/ `config/sanctum.php` stateful / `bootstrap/app.php` Sanctum 設定 / `resources/views/notifications/_partials/notification-popover.blade.php` / `resources/js/notification/notification-popover.js` + `resources/js/utils/fetch-json.js` / `resources/js/app.js` |

### 結論サマリ

| ID | Feature | レビュー | バグ箇所のレイヤー | 備考 |
|---|---|---|---|---|
| S-A-01 | mentoring | ✅ 適切 | Action / Service(`Coach*Action` / `GoogleCalendarService` / `GoogleOAuthService` / `MeetingAvailabilityService::slotsForCertification` / `Meeting\{Store,Cancel}Action`)| 2 Service 分割(認可フロー / Calendar 操作)+ `DB::afterCommit` での GCal API 呼出 + 失敗フォールバック + トークン自動 refresh が `backend-repositories.md` / `backend-services.md` 規約準拠。`final` 不採用は Mockery 互換性のため(`backend-services.md` 規約)。Step 4 = 全 GCal 関連コード(table + Model + Service + Action + Controller + 既存 Action の組込み + migration)を引き算で剥がし、提供 PJ では mentoring base のみで完結する状態に巻き戻す |
| S-A-02 | ai-chat | ✅ 適切 | Repository / Action / Service(`GeminiLlmRepository` / `AiChat*Action` + `AiChatMessage\StoreAction` + `AiChatPromptBuilderService`)| Repository パターンで LLM 抽象化(`LlmRepositoryInterface`)+ `final readonly` DTO(`LlmChatResponse`)+ Transaction A 先行 commit パターン(失敗時の受講生入力保持)+ タイトル LLM 自動生成 + Rate Limit + 機能 OFF スイッチ + API キー未設定時防衛 が綺麗な構成。Step 4 = 全 AI 関連コード(table 2 件 + Enum + Repository + Service + Action + Controller + Policy + Exception 6 件 + JS + config + composer 追加)を引き算で剥がす |
| S-A-03 | meeting-quota | ✅ 適切 | Action / Middleware(`CreateCheckoutSessionAction` / `StripeWebhook\HandleAction` / `PurchaseQuotaAction` / `VerifyStripeSignature`)| `meeting_quota_transactions` 基盤は **提供 PJ 既存**(B-A-01 / B-B-10 が前提)で、本チケットは Stripe 購入動線のみを追加する構造。Webhook 冪等性 = `lockForUpdate` + `status` 遷移ガード + `WHERE status=pending` 条件で「成功 → 失敗逆順到着」「同イベント再送」両方への耐性。Step 4 = Payment table + Stripe 関連コード(Action 3 件 + Controller 2 件 + Middleware + Policy + Exception 3 件)を引き算で剥がす |
| S-A-04 | certification-management | ✅ 適切 | Service / Action(`CertificatePdfService` / `CertificateSerialNumberService` / `Certificate\{Issue,Download}Action`)| `certificates` table + 修了証 INSERT は **提供 PJ 既存**(`ReceiveCertificateAction` から呼ばれる)で、本チケットは **PDF 生成 + Storage 保存 + DL endpoint** を追加する構造。月内連番 `lockForUpdate` + PDF 生成失敗時の DB ROLLBACK + Storage 保険削除 + Service Mockery 不採用(`final` 外し)が `backend-exceptions.md` / `backend-services.md` 規約準拠。Step 4 = PDF 生成部分 + DL Action + Controller + Policy + Blade + Service 2 件 + Exception 2 件 + composer 追加 を引き算で剥がし、提供 PJ では Certificate INSERT のみで `pdf_path` がパス予約のみの状態に巻き戻す |
| S-A-05 | notification | ✅ 適切 | Sanctum + JS(`Api\V1\NotificationController` / `Api\IndexAction` / `Api\MarkAllAsReadAction` / `NotificationResource` / `notification-popover.js` + `fetch-json.js` + `notification-popover.blade.php`)| `routes/api.php` の `v1` group に `auth:sanctum` 後付け(`S-B-05` の認証なし API の上に積む)+ JS フロントで `/sanctum/csrf-cookie` → `fetch(..., { credentials: 'include' })` + `X-CSRF-TOKEN` ヘッダの CSRF 二段防御 + 通知ポップオーバー(タブ切替 + ローディング + 行クリック既読化 + 遷移 + 全件既読 + フッターリンク)+ a11y(`role="dialog"` + ESC + フォーカス戻し)。Step 4 = `auth:sanctum` Middleware の取り外し + JS フロント 2 ファイル削除 + ポップオーバー Blade 削除 + Resource / API IndexAction 削除 を引き算で剥がし、`S-B-05` の認証なし API + 静的ベル UI に巻き戻す |

### 評価ポイント

- **5 件すべて Advance 範囲(Action / Service / Repository / 外部 API / Sanctum / JS)** で、構造上の Basic 例外注記なし
- **S-A-01 と S-A-03 と S-A-04 で外部 API 連携パターンを 3 種類網羅**: S-A-01 = `google/apiclient` SDK(Calendar API)+ OAuth Cookie 認証 / S-A-03 = `stripe/stripe-php` SDK(Checkout + Webhook 受信)+ HMAC 署名検証 / S-A-04 = `mpdf/mpdf` ライブラリ(PDF 生成のローカル処理)+ Storage 書き込み。「外部 API への OAuth クライアント」「外部 API からの Webhook 受信」「ローカルライブラリ for 出力」の 3 系統を学習素材として並列配置
- **S-A-02 と S-A-05 で Sanctum + JS フロント連携パターンを 2 軸網羅**: S-A-02 = AI 相談機能の JS フロント(`fetch` + ストリーム表示なしの同期版 + フローティングウィジェット + セッションストレージ状態保持)/ S-A-05 = 通知ポップオーバーの JS フロント(`fetch` + `auth:sanctum` Cookie 認証 + CSRF Cookie + タブ切替 + ローディング + 行クリック既読化 + a11y)。両者ともセッション認証ベースの Cookie 認証で、API トークンは使わない方針で統一(`tech.md` 規約準拠)
- **S-A-03 と S-A-04 のテーブル所有関係**: S-A-03 は `payments` table 新規 + `meeting_quota_transactions` 既存利用 / S-A-04 は `certificates` table 既存利用(提供 PJ 段階で空の `pdf_path` 予約) + PDF 実体追加。両者とも「提供 PJ 段階で DB スキーマは既に存在し、本チケットで実体ロジックを追加する」構造で、Story Advance のサブカテゴリ「既存機能の拡張」の典型例
- **S-A-02 の Rate Limit 採用方針**: Laravel 標準 `RateLimiter::for('ai-chat')` のみで日次上限を構成 + 自前クォータ補正は不採用(Gemini RPM 圧迫を防ぐ運用判断)。S-A-03 の Stripe Webhook 冪等性ガードと比較して「失敗をどう扱うか」の方針が明確
- **S-A-05 の `auth:sanctum` 後付け階段**: `S-B-05`(Basic、認証なし API)→ `S-A-05`(Advance、Sanctum 認証後付け)の 2 段階階段は BookShelf 流の教育パターンを踏襲。`SANCTUM_STATEFUL_DOMAINS` 設定の感覚を養う目的で、同一オリジン構成での Cookie 認証を採用

### 依存関係メモ

| チケット | 依存 | 補足 |
|---|---|---|
| S-A-01 | なし | 提供 PJ の mentoring base(`Meeting\StoreAction` / `CancelAction` / `MeetingAvailabilityService` 等)を前提とするが、依存チケットなし(完成形 mentoring base はチケット化されていない) |
| S-A-02 | なし | `EnsureActiveLearning` Middleware / `User.default_enrollment_id` カラム / `Section` Model 等の依存は提供 PJ 基盤側 |
| S-A-03 | `S-B-02`(面談パックマスタ管理) | `MeetingPack` テーブル + 公開状態の SKU が前提 |
| S-A-04 | なし | `certificates` table + `Certificate` Model + `Enrollment.status=passed` の状態遷移 + `ReceiveCertificateAction`(`Enrollment` Feature)は提供 PJ 既存 |
| S-A-05 | `S-B-05`(通知 JSON API(認証なし)) | `routes/api.php` の `/api/v1/notifications*` 3 ルート + `NotificationResource`(Web 経由)が前提、本チケットで `auth:sanctum` 後付け |

→ ✅ 5 件すべて適切(即時修正不要、Phase D 横断課題もなし)。本チケット詳細化と Task Advance 詳細化(T-A-01〜T-A-04)が完全に整合し、依存方向(Story Advance → 完成形提供 PJ → Task Advance 巻き戻し)が破綻なし

---

## B-A-02 / S-B-06 / B-A-03 再生成(2026-05-28、新構造へ移行)

2026-05-25 詳細化の 3 件を新テンプレ・規約に移行して再生成。再生成モードのため Step 3(模範解答 PJ 懐疑的レビュー)は既存判定を尊重しスキップ。模範解答 PJ コードは詳細化当時から変更なしを確認(`GradeAction` / `SubmitAction` / `TermJudgementService` / `EnrollmentGoal*` 一式を再 Read)。

### B-A-02 / mock-exam-04(Bug Advance)

- 旧 4 サブセクション(主要 URL / 原因箇所メモ / 採用技術と判断理由 / テスト方針)を **Bug 新テンプレ「原因のみ」** に再編
- 構築側メタ表現(「仕込み内容(Step 4 引き算)」+ `round(... * 100, 2)` の修正コード片)を除去 → 業務語彙 + クラス/メソッド名併記の原因要約に置換(Step 4 仕込み詳細は README 列が SSoT、規約 2.3)
- テスト実装 AC を追加、AC 5→4 件(合否判定 + スケール一貫表示 + 周辺挙動維持 + テスト)に圧縮
- 波及(`WeaknessAnalysisService` の弱点ヒートマップ / 合格可能性スコアが 0〜100 スケール前提)は原因の「※ Service 内」注記 + Q&A に集約
- 模範解答 PJ: `GradeAction:53-56` の百分率変換 + 合否判定は適切 ✅(コード変更不要)

### B-A-03 / enrollment-04(Bug Advance)

- 同じく旧 4 サブセクション → **Bug 新テンプレ「原因のみ」** に再編、構築側メタ(`whereIn` への `canceled` 追加という Step 4 表現)を業務語彙の原因要約に置換
- テスト実装 AC を追加 + 状態判定 AC を統合(基礎/実践ターム判定 を 1 行に)、AC 4 件(判定統合 + 開始時実践化 + 不要 UPDATE スキップ + テスト)
- 模範解答 PJ: `TermJudgementService:27-30` の状態集合(`in_progress` / `submitted` / `graded`)+ 同一ターム時 UPDATE スキップは適切 ✅(コード変更不要)

### S-B-06 / enrollment-03(Story Basic)

- **実装方針 5 サブセクション構造**(インターフェース[認可列] → データモデル[制約列] → コンポーネント → 異常系 → 設計判断[テスト観点内包])に再編
- 旧「やること / やらないこと」→「要件 / スコープ外」に改名、要件から HTTP ステータス(403)を除去し AC へ移動。冒頭の重複「概要」節を削除(Story は背景・目的から開始)
- AC 19→8 件に圧縮(追加 / 編集 / 削除 / 達成トグル[べき等統合] / 一覧+認可出し分け / 認可拒否[機能群統合] / バリデーション / テスト)
- ⚠️→**事実修正**: 旧版は独立「Seeder 設計」節 + `EnrollmentGoalSeeder` を前提にしていたが、模範解答 PJ に `EnrollmentGoalSeeder` は存在せず、目標は **`EnrollmentSeeder` 内で受講登録生成と同時に投入**(`EnrollmentSeeder:113-118`、`Enrollment::goals()` 経由で達成済 + 未達成を生成)。独立節を廃止しデータモデル > 初期データ Seeder に統合 + Seeder 名を事実に合わせて修正
- 旧版が参照した View パス(`enrollment/_partials/goal-list` 等)を実在パス(`enrollment-goal/edit.blade.php` + `_form.blade.php`、一覧は `enrollments.show` 内)に修正
- 模範解答 PJ: `EnrollmentGoalPolicy`(view 委譲 + 本人判定)/ `MarkAchievedAction`(無条件 UPDATE = 冪等)/ FK `cascadeOnDelete` + 物理削除 は適切 ✅(コード変更不要)
- Basic 範囲: Action(`EnrollmentGoal\*Action`)はコンポーネント節で「※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可」注記

---

## T-B-03 / T-A-04 再生成(2026-05-28、新 Task 構造で書き直し + 模範解答 PJ 再確認)

### 経緯

`templates/task.md` + 規約 §2.4 Task 専用節の刷新(実装方針を単一「変更内容」に集約 / 「やること・やらないこと」→「要件・スコープ外」/ Task は 背景・目的 を概要に統合 / AC フラット化 1-3 件 / 構築側メタ[Step 4 表現]除去)に合わせ、2026-05-25 詳細化版を新 Task 構造へ移行。再生成にあたり対象コードを再 Read し、旧版の技術名の誤りを修正。

### T-B-03 / horizontal-01(✅ 適切、新構造へ移行 + 旧版の Action クラス名誤りを修正)

- 模範解答 PJ の対象 3 コマンドを再 Read で確認 — いずれも `->orderBy('id')->chunkById(100, function ($items): void {...})` で実装済(`GraduateExpiredUsersCommand` / `FailExpiredEnrollmentsCommand` / `Mentoring\AutoCompleteMeetingsCommand`)。3 件とも `foreach` 内で WHERE 列(`status`)を更新するため `chunkById()` 必須の教材性は維持。
- **旧版の誤り修正**: 旧 100% 版「改善対象コードメモ」が関連 Action を `User\GraduateAction` / `Enrollment\FailAction` / `Meeting\AutoCompleteAction` と記載していたが、実装は `Plan\GraduateUserAction`(修了)/ Action を介さずコマンド内 `DB::transaction` + `EnrollmentStatusChangeService` + `DefaultEnrollmentService`(不合格)/ `Meeting\AutoCompleteMeetingAction`(面談完了)が正。新版「変更内容」で実装準拠に修正。
- **対象外コマンドの参照修正**: 旧版が `SessionCloseService::closeStaleSessions()` と記載していた箇所は、実装は `Learning\CloseStaleSessionsCommand` → `App\UseCases\LearningSession\CloseStaleSessionsAction`(`lockForUpdate` で一括ロック)が正。スコープ外は業務語彙(滞留学習セッションの強制クローズ)で記述しクラス名の誤りを排除。
- `cursor()` 用例不在は Phase D で「概念教育のままで確定」済(本ログ上部参照)。新版も採用技術判断理由 + Q&A で使い分けの一般則を概念教育する形を維持。
- → ✅ 新 Task 構造で完結。AC 3 件(分割処理 + 完走 / 取りこぼし防止 / テスト実装)。

### T-A-04 / horizontal-04(⚠️ 模範解答 PJ 未実装ギャップ検出 — Phase D / 模範解答完成タスクで要対応)

再生成にあたり模範解答 PJ のテスト現状を `find` / `grep` で再確認したところ、本チケットが「完成形(= 引き算前の理想状態)」として記述しているテスト資産のうち **3 点が模範解答 PJ に未実装** であることを検出。引き算方式では「模範解答 PJ に完成形があり、Step 4 でそれを削って提供 PJ の before 状態を作る」のが前提だが、以下は削る対象が存在しない:

| # | 未実装ギャップ | 現状 | 影響 |
|---|---|---|---|
| 1 | `tests/Unit/Services/Google/GoogleOAuthServiceTest.php` / `GoogleCalendarServiceTest.php` | ディレクトリごと不在(`tests/Unit/Services/Google/` なし) | Google 連携 Service の単体テスト(認可フロー / Calendar 操作の正常系 + 期限切れリフレッシュ + フォールバック + 410 Gone)が模範解答に無い。利用側 Mockery テスト(`MeetingAvailabilityServiceTest` / `Meeting\{Store,Cancel}ActionTest` / `CoachGoogleCredentialControllerTest`)は存在 |
| 2 | `Http::preventStrayRequests()` | `tests/` 全体で 0 件(`TestCase.php` も未組込) | 未モック通信の遮断ラインが無い。`Http::fake` 漏れ時に実 API を叩くリスクが残る |
| 3 | `#[Group('external')]` | `tests/` 全体で 0 件(`GeminiLlmRepositoryTest` にも未付与) | `backend-tests.md` は `GeminiLlmRepositoryTest` への付与を例示しているが実コードは未付与。`--exclude-group external` の分離実行ができない |

- **実装済(削る対象が存在する)**: `GeminiLlmRepositoryTest`(`Http::fake` / `fakeSequence` / `assertSent`、5 ケース)/ `StripeWebhookControllerTest`(`private sign()` HMAC ヘルパー + 有効/無効/欠落/冪等性 4 ケース)/ 利用側 Service レベル Mockery テスト 5 ファイル / Google 2 Service の `final` 不採用 + 理由コメント。
- **本チケット側の扱い**: 100% 版は「完成形」を記述する責務なので、上記 3 点を含めた理想状態を記述したまま再生成済(変更後 = 理想形、変更前 = 提供 PJ の薄い状態)。ただし模範解答 PJ が完成形に達していないため、**Step 4 引き算の前に模範解答 PJ 側でギャップ #1〜#3 を埋める必要がある**(または本チケットのスコープを実装済資産[Gemini / Stripe + 利用側 Mockery]に縮小するか、ユーザー判断)。新版 README 完成済みリストにも ⚠️ で明記。
- **判定**: ⚠️ 修正必要(模範解答 PJ 未完成)。純粋なチケット記述の問題ではなく模範解答 PJ の完成度ギャップのため、Phase D / 模範解答完成工程での対応を推奨。再生成自体は新 Task 構造で完結(AC 3 件)。

---

## 2026-05-28 再生成: S-B-02 / S-A-03 / B-B-10(meeting-quota Feature、実装方針 5 サブセクション + Bug 新テンプレ)

`/ticket-detail-100p S-A-03, S-B-02, B-B-10` で meeting-quota Feature の 3 チケットを新構造へ再生成(再生成モード = 保持セクション維持 + 受け入れ条件 / 実装方針 / Q&A を再生成、Step 3 懐疑的レビューは既存判定を尊重しスキップ)。模範解答 PJ コードを Read で再確認した上で記述。

### S-B-02 / meeting-quota-01(⚠️→訂正完了: 削除方式 SoftDelete → 物理削除)

- **実装方針を 5 サブセクションへ再編**: インターフェース(認可列)→ データモデル(既存 meeting_packs、変更不要マーク + 制約列)→ コンポーネント(クラス + パス集約、Action は ※ Basic 範囲外注記)→ 異常系 → 設計判断(テスト観点内包)。やること/やらないこと→要件/スコープ外 改名 + 概要を背景・目的へ統合。AC 13→8 件(新規作成と編集は別操作のため非統合、認可は機能群 1 件統合)。
- **⚠️→訂正完了(削除方式)**: 既存チケット・本 _review-log 上部(S-B-02 結論表)の双方が「下書き / アーカイブのみ **SoftDelete**」と記載していたが、模範解答 PJ 実装は **物理削除** が正。根拠 — ① `app/Models/MeetingPack.php` は `use HasFactory, HasUlids` のみで **SoftDeletes trait なし**、② `2026_05_17_000010_create_meeting_packs_table.php` に **`softDeletes()` / `deleted_at` カラムなし**(`timestamps()` のみ)、③ `DestroyAction` は `$plan->delete()`(trait 無しのため物理削除)、④ `backend-models.md`「マスタ系で Draft/Published/Archived status を持つ Entity は SoftDelete 不採用」に準拠、⑤ 姉妹 `Plan`(S-B-03)も物理削除 + _review-log で ✅ 確認済。**ユーザー確認(2026-05-28 AskUserQuestion)で「物理削除に統一(チケットを実装+規約に合わせる、コード変更なし)」を選択**。チケット(データモデル / AC / Q&A / 設計判断)を物理削除に訂正、購入履歴の整合性は ① 公開中削除ガード(409)+ ② `payments` の FK `restrictOnDelete`(購入履歴を持つパックは物理削除がブロックされる)の二重で担保すると明記。本 _review-log 結論表(削除制約の行)も訂正済。
- → ✅ 訂正完了。実装変更なし(チケット + _review-log のドキュメント訂正のみ)。

### S-A-03 / meeting-quota-02(✅ 適切、実装準拠で事実修正 2 点)

- **実装方針を 5 サブセクションへ再編** + やること/やらないこと→要件/スコープ外 + 概要を背景・目的へ統合 + 独立「Seeder 設計」節を データモデル > 初期データ Seeder(必須)へ統合。AC 18→10 件(規模大上限、Webhook 冪等性 + 識別子一意性 + 逆順耐性を 1 件に統合 / 認可を機能群 1 件 / 署名検証 成功+失敗を 1 件)。
- **事実修正 1(Seeder)**: 旧「Seeder 設計」節は payment_1/2/3(受講生 A/B/C)の理想シナリオを記載していたが、実 `PaymentSeeder` は **固定 student(succeeded + pending + admin_grant)+ demo 受講生 × 6(succeeded/succeeded/pending/failed/refunded/admin_grant 循環)** が正。依存順序 `UserSeeder → MeetingPackSeeder → MentoringSeeder → PaymentSeeder`。実装準拠に修正。
- **事実修正 2(Laravel バージョン構成)**: 旧「アーキテクチャ判断 §5」は CSRF 除外 / Middleware 登録を「`bootstrap/app.php`(Laravel 11+)」と記載していたが、本 PJ は **Laravel 10**(`bootstrap/app.php` は Kernel ベースの旧構成)。実際の所在は `app/Http/Kernel.php` の `$middlewareAliases`(`'stripe.signature' => VerifyStripeSignature::class`)+ `app/Http/Middleware/VerifyCsrfToken.php` の `$except`(`'webhooks/stripe'`)。設計判断 / コンポーネント節を Laravel 10 構成に訂正。
- 実装確認: `Payment` は SoftDeletes trait + migration `softDeletes()` あり(会計監査要件、`backend-models.md`「Payment は SoftDelete 採用」と整合、MeetingPack とは判断軸が異なる)。`MeetingQuotaPolicy::purchase`(student + in_progress)/ `CheckoutRequest`(exists where status=published)/ `StripeWebhook\HandleAction`(lockForUpdate 冪等性 + WHERE status=pending の逆順耐性)/ 3 例外(400/422/403、`UserNotInProgressException` のみ `render()` で HTML redirect)— いずれも記述通りで ✅。
- → ✅ 適切。実装変更なし(チケットの事実修正のみ)。

### B-B-10 / meeting-quota-04(✅ 適切、Bug 新テンプレへ再編)

- **実装方針を Bug 新テンプレ[原因のみ]へ再編**: 主要 URL 表を廃止し原因節に統合、期待する動作/実際の動作→期待する結果/実際の結果 に改名、テスト実装 AC 追加(AC 2→3 件)。長文の ※ Basic 範囲外注記は概要直下に維持(`Meeting\CancelAction` = Action 層が原因 + Basic 受講生の Controller 内完結代替を案内)。
- 実装確認: `Meeting\CancelAction`(`DB::transaction` + `lockForUpdate` + reserved 限定 + `scheduled_at > now` ガード)が `($this->refundAction)($locked->student, $locked->id)` で `MeetingQuota\RefundQuotaAction`(`MeetingQuotaTransaction(type=Refunded, amount=1)` INSERT)を同一 Tx 内で呼ぶ。残数集計は `MeetingQuotaService::remaining`(= `User.max_meetings + SUM(消費/返却/購入/管理者付与)`)。Step 4 = この refund 呼び出し 1 行削除で状態遷移のみ成立し残数が戻らない。完了フラッシュ「面談をキャンセルしました。面談回数を返却しました。」が出る↔残数不変の食い違いを手がかりとして原因節に明示。
- → ✅ 適切。実装変更なし。

---

## 2026-05-28 再生成: B-B-06 / B-B-12 / B-B-13 / B-B-15 / B-B-16(auth Feature、Bug 新テンプレ + qa-board 横断整合)

`/ticket-detail-100p B-B-06, B-B-12, B-B-13, B-B-15, B-B-16` で auth Feature の Bug Basic 5 件を新構造へ再生成(再生成モード = メタ情報 / 概要 / 再現手順〜実際の結果 を保持、受け入れ条件 / 実装方針 / Q&A を再生成、Step 3 懐疑的レビューは既存判定[B-B-06/12/13/16 ✅、B-B-15 差し替え済]を尊重しスキップ)。模範解答 PJ コード(`Auth\OnboardAction` / `OnboardingRequest` / `EnsureUserRole` / `EnsureActiveLearning` / `Actions\Fortify\AuthenticateUserUsing` / `routes/web.php`)を Read で再確認。

### 共通の再生成内容(5 件)

- 実装方針を **Bug 新テンプレ[原因のみ]** へ再編(主要 URL 表を廃止し原因節 = 主要ファイル / 仕込み内容 / 修正範囲 に統合)、期待する動作 / 実際の動作 → **期待する結果 / 実際の結果** に改名、冒頭の HTML コメント(記述粒度規約)除去、**テスト実装 AC 追加**(全件 AC 3 件)。
- B-B-06 / B-B-12(原因 = `Auth\OnboardAction` = Action 層)は ※ Basic 範囲外注記を **原因節にインライン短縮**(長文の冒頭独立 blockquote を廃止、規約 6.1 単一ファイル時の短注記原則に準拠)。B-B-13(FormRequest)/ B-B-15(route + Middleware)/ B-B-16(Middleware)は Basic 範囲のため注記なし。
- B-B-12: 原因に ログイン認証 `AuthenticateUserUsing`(in_progress / graduated のみ通過)+ `EnsureActiveLearning` 参照を明記(status カラムと監査ログの食い違い + 完了直後の自動ログインだけ通る症状の機序)。
- B-B-15: Q&A の `B-B-01` 交差参照(規約 2.3 別チケット言及禁止)を除去し概念説明に置換。

### ⚠️→対応完了: B-B-16 × S-B-01 の qa-board active-learning 矛盾(横断整合)

- **検出**: B-B-16(`EnsureActiveLearning` ガード漏れ)の対象機能に qa-board を含めるか確認中、模範解答 PJ 内の矛盾を検出。**実装**(`routes/web.php` L578 の qa-board 公開エンドポイント group に `active-learning` 適用 + `EnsureActiveLearning` docblock が qa-board を guarded plan feature として明示列挙)では qa-board は受講中のみ・修了 403。一方 **S-B-01**(2026-05-28 再生成版)は「qa-board は学習資産として永続閲覧を許容、`EnsureActiveLearning` 非適用、修了済もアクセス可」と 3 箇所(要件 / 実装方針ミドルウェア / Q&A)で明記しており食い違い。
- **判断軸**(統一感 / UX): qa-board の姉妹プラン機能 — 教材閲覧(`learning`)/ チャット(`chat`)/ AI 相談(`ai-chat`)— は **すべて active-learning 適用**。特に教材閲覧(= 最も「学習資産」的なコンテンツ)自体が修了で 403 になる以上、その上の議論層 qa-board だけを永続開放するのは不整合。docblock も qa-board を元から列挙。→ **qa-board を guarded に統一**(= 実装現状維持、コード変更なし)が一貫モデル。
- **ユーザー判断**(2026-05-28 AskUserQuestion): 「統一感と UX 的に適切な方で」+「qa-board 以外の機能はどうなっている?」→ active-learning マップ(guarded: 教材 / 演習 / 模試 / 面談予約 / 追加面談購入 / chat / qa-board / ai-chat、非適用: dashboard / プロフィール / 通知 / 修了証DL / 受講登録閲覧 / 面談詳細・キャンセル)を提示し guarded 統一を採用。
- **対応内容**:
  - B-B-16: qa-board を対象機能リストに維持(概要 / 再現手順 / AC / 原因 / Q&A)、Q&A に「qa-board・チャットも対象(教材・模試・面談と同じ扱い)」を追記。
  - S-B-01: 矛盾 3 箇所を「公開エンドポイントは受講中のみ(`role:student,coach` + `active-learning`)、修了 / 退会は 403、管理者モデレーションは active-learning 対象外」へ訂正。
- → ✅ 対応完了。**実装変更なし**(実装は元から qa-board を guarded、S-B-01 ドキュメント側を実装へ整合 + B-B-16 を整合維持)。
