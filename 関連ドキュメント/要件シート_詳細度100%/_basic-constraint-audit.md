# Certify LMS 全 40 チケット Basic 制約棚卸し (2026-05-26)

> P2 棚卸し成果物。Bug 階段化方針(Basic→Controller / Advance 入門→Action / Advance 応用→Service)の採用判断 + 模範解答 PJ リファクタ要否判断の入力資料。
>
> 構築側メタ情報、受講生・採点者には渡さない。Phase D 一括処理(`_review-log.md` 連携)の追加判断材料として活用。

**2026-05-30 追記**: チケット集合はその後 42 件に増加(Queue Task `T-A-05_horizontal-05` を追加、Advance のため Basic 制約の対象外)。S-A-04 の証書番号削除は難易度不変(Advance 維持)。本棚卸し以降の追加分も含め、**Basic チケットは全て Basic 範囲内に収まる**結論は不変(以下のサマリは作成時点 [40 件 / Story 14] の数値)。

## サマリ

### Story 14 件

- Basic Story 9 件 (S-B-01〜S-B-09) / Advance Story 5 件 (S-A-01〜S-A-05)
- Basic Story で Action(UseCases)使用が明記: **9 件 (全 Basic Story)**
- Basic Story で Service 使用が明記: 1 件 (S-B-07 の Fortify Action はサービス相当)
- Basic Story で JS 使用 Blade: 0 件 (Basic Blade は JS なし方針で統一)
- Advance Story で外部 API: 5 件 (Google Calendar / Gemini / Stripe / Sanctum / mpdf)
- Advance Story で JS 使用 Blade: 2 件 (S-A-02 / S-A-05)

### Bug 19 件 — **本棚卸しの中核**

- Basic Bug 16 件 / Advance Bug 3 件
- 仕込み層別:

| 層 | 件数 | チケット ID |
|---|---:|---|
| Controller | 2 | B-B-07, B-B-08 |
| Controller 内分岐 | 1 | B-B-09 |
| FormRequest | 1 | B-B-13 |
| Middleware | 1 | B-B-16 |
| Route(routes/web.php) | 1 | B-B-15 |
| Policy | 1 | B-B-01 |
| **Action (UseCases)** | **9** | B-B-02, B-B-03, B-B-04, B-B-05, B-B-06, B-B-10, B-B-11, B-B-12 (Basic) + B-A-01 (Advance) |
| **Service** | **3** | B-B-14 (Basic) + B-A-02, B-A-03 (Advance) |

- Basic Bug × Controller/FormRequest/Policy/Middleware/Route: **7 件 ✅ 階段適合**
- Basic Bug × Action: **9 件 ⚠️ 階段方針なら要対応** (全件「※ Basic 範囲外への例外注記」付き)
- Basic Bug × Service: **1 件 ⚠️⚠️ 最も逸脱** (B-B-14 ChatUnreadCountService)
- Advance Bug × Action: 1 件 (B-A-01) — 階段適合
- Advance Bug × Service: 2 件 (B-A-02, B-A-03) — 階段適合

### Task 7 件

- Basic Task 3 件 (T-B-01〜T-B-03) / Advance Task 4 件 (T-A-01〜T-A-04)
- 対象層別:
  - Basic: Action 内クエリ修正 2 件 (T-B-01, T-B-02、いずれも「Controller 内完結も可」例外注記あり)、Schedule Command 修正 1 件 (T-B-03)
  - Advance: Service 修正 1 件 (T-A-01)、Service 分離 1 件 (T-A-03)、Controller→Action 分離 1 件 (T-A-02)、テスト追加横断 1 件 (T-A-04)

## 推奨アクション(優先度順)

### 1. Bug 階段化方針を正式採用するなら、Basic Bug 10 件の仕込み位置を Controller / FormRequest / Policy / Middleware / Route 内に強制リファクタする規約変更が必要

現状の 16 件の Basic Bug のうち、10 件 (Action 9 件 + Service 1 件) は Action / Service 内仕込み。例外注記でカバーしているが、「Basic 受講生は Controller 内完結で実装してよく、その場合は対応する Controller メソッドを修正対象とする」という二段運用は採点バラつきを生む。

### 2. 模範解答 PJ を「Basic 範囲チケット対象箇所だけ Controller 巻き戻し」する工数感

- **Story 9 件**: 全件「Action 採用は受講生判断、Controller 内完結も可」と明記済み。Controller 巻き戻しすると Story 9 件 × 平均 3-5 Action ≒ 30-45 Action を Controller method 内ロジックに inline する必要。1 Action 30-60 分換算で **15〜25 時間規模**
- **Bug 10 件 (Action 9 + Service 1)**: Bug は「仕込み 1 行」が本質なので、巻き戻すなら「該当業務ロジックを Controller 内に inline + そこにバグを仕込み直す」工数。Action 9 件 × 0.5〜1 時間 + Service 1 件 × 1〜2 時間 ≒ **6〜11 時間**
- **Task 2 件 (T-B-01 / T-B-02)**: 同上の「Controller 内完結も可」明記済。N+1 修正は inline 化しても 1 行レベルの修正で済むため、Controller 化済みなら本 Task の難易度は下がる
- **合計**: 全 Basic 対象を巻き戻すなら約 **21〜36 時間**。Feature 単位で巻き戻せば実質 **15〜25 時間程度** に圧縮可能(同 Action を Story と Bug で共有しているケースが多い、例: B-B-04 と T-B-01 は同じ `User\IndexAction`)

### 3. Basic Bug × Service の B-B-14 (ChatUnreadCountService) は最も逸脱が大きい

Service 層に仕込まれているため Bug 階段化方針では「Advance Bug 応用」相当。現状は「Basic 受講生は Controller / View Composer 内で集計しても良い」と例外注記しているが、未読集計ロジックが複数箇所(`SidebarBadgeComposer` / `ChatRoomController::show`)で共有されている都合上、Service 分離せずに重複コードを書かせる構造になる。

選択肢:
- **A**: Advance Bug 応用カテゴリに格上げ
- **B**: Service 共通化が必要な集計を扱う特殊な Basic Bug として例外運用継続
- **C**: 模範解答 PJ の集計ロジック構造を抜本変更(View Composer 廃止 + 全箇所で集計重複コード化)

### 4. S-B-04 (notification 基盤) は通知発火フックが他 Feature の Action 内に埋め込まれる特殊構造

発火フックは `chat StoreMessageAction` / `qa-board QaReply\StoreAction` / `mentoring Meeting\StoreAction` / `Meeting\CancelAction` 内に存在 = 他 Feature の Action 修正が必要。Basic 受講生実装方針は「Controller 内で $user->notify() 直接呼出も可」と注記済(本チケット最大の例外運用)。

階段化採用なら、発火経路を Controller method に集約する大規模リファクタが必要。難易度が高い。

### 5. Advance Bug 3 件は階段方針に完全整合済

- B-A-01: ConsumeQuotaAction 内 lockForUpdate 削除 → 階段「Advance 入門」適合
- B-A-02: GradeAction 内 + WeaknessAnalysisService 波及 → 階段「Advance 応用」適合
- B-A-03: TermJudgementService のクエリ条件誤り → 階段「Advance 応用」適合

→ Advance 側は問題なし。リファクタ判断は Basic 側のみで完結する。

## 全件詳細

### Story (14 件)

| ID | Feature | 難易度 | 主なアーキ層 | JS | Basic 制約 | 備考・対応提案 |
|---|---|---|---|---|---|---|
| S-A-01 | mentoring | Advance | Action + Service(GoogleOAuth / GoogleCalendar) + Middleware + Repository | なし | N/A | Advance 範囲、Google OAuth + 外部 API 連携、Service 二層化と Action 統合は妥当 |
| S-A-02 | ai-chat | Advance | Action + Service(PromptBuilder) + Repository(Gemini) + JS | あり (`resources/js/ai-chat/`) | N/A | Advance 範囲、Gemini API + フローティングウィジェット JS は Advance 適合 |
| S-A-03 | meeting-quota | Advance | Action + Service(StripeClient binding) + Middleware(VerifyStripeSignature) | なし | N/A | Advance 範囲、Stripe Webhook 署名検証 + Webhook 冪等性ガード |
| S-A-04 | certification-management | Advance | Action + Service(CertificatePdf / SerialNumber) + Policy + mpdf | なし | N/A | Advance 範囲、PDF 生成 Service 2 種 + Action 統合は妥当 |
| S-A-05 | notification | Advance | Action(Api/*) + Resource + Middleware(Sanctum) + JS | あり (`resources/js/notification/`) | N/A | Advance 範囲、Sanctum SPA Cookie + JS fetch + ポップオーバー |
| S-B-01 | qa-board | Basic | Controller + 7 Action + 3 Action + Policy + FormRequest + Blade(JSなし) | なし | ⚠️ Action 多数使用 | QaThread / QaReply 配下 10 Action 採用。Controller 内完結化巻き戻しなら 10 Action 分のロジックを 2 Controller 内 method に inline 必要(規模大、3-5 時間) |
| S-B-02 | meeting-quota | Basic | Controller + 8 Action(MeetingPack 配下) + Policy + FormRequest | なし | ⚠️ Action 多数使用 | CRUD + 状態遷移 3 件のフラット Controller への巻き戻しは比較的容易(2-3 時間) |
| S-B-03 | plan-management | Basic | Controller + 8 Action(Plan 配下) + Policy + FormRequest | なし | ⚠️ Action 多数使用 | S-B-02 とほぼ同型、巻き戻し工数も同等 |
| S-B-04 | notification | Basic | Controller + 7 Action(Notification/Notify*) + Notification class + Policy + View Composer | なし | ⚠️⚠️ Action + 発火フックが他 Feature の Action 内 | 発火フックは chat / qa-board / mentoring の Action 内 = 他 Feature の Action 修正が必要。階段化採用なら発火経路を Controller に集約する大規模リファクタが必要 |
| S-B-05 | notification | Basic | Controller + 2 Action(Api/Index, Api/MarkAllAsRead) + Resource + FormRequest | なし | ⚠️ Action 使用 | API 用 Action 2 件、Controller 内完結化容易(1 時間) |
| S-B-06 | enrollment | Basic | Controller + 5 Action(EnrollmentGoal 配下) + Policy + FormRequest | なし | ⚠️ Action 多数使用 | 5 Action の巻き戻し容易(2 時間) |
| S-B-07 | settings-profile | Basic | Controller + Profile/Avatar Action + Fortify Action + Policy | なし | ⚠️ Action 使用 + Fortify Action は OK | Fortify Action は教材スコープで OK。Profile / Avatar 系 3 Action の巻き戻し |
| S-B-08 | mentoring | Basic | Controller + 3 Action(EnrollmentNote 配下) + Policy + FormRequest | なし | ⚠️ Action 使用 | 3 Action の巻き戻し容易(1.5 時間) |
| S-B-09 | notification | Basic | Controller + 4 Action(Announcement Index/Show/Store + NotifyAnnouncementAction) + Notification + Policy | なし | ⚠️ Action 使用 + 配信ループの Action 化 | 配信ロジック(receiver 集合解決 + each で notify)を Controller 内に書ける(注記済)。階段化採用しても比較的容易(2 時間) |

### Bug (19 件)

| ID | Feature | 難易度 | 原因主要ファイル | アーキ層 | Bug 階段適合 | 備考・対応提案 |
|---|---|---|---|---|---|---|
| B-A-01 | mentoring | Advance | `app/UseCases/MeetingQuota/ConsumeQuotaAction.php` | Action | ✅ Advance 入門 | 並行性 lockForUpdate 削除。Action 内完結 |
| B-A-02 | mock-exam | Advance | `app/UseCases/MockExamSession/GradeAction.php` (+ 波及 `WeaknessAnalysisService`) | Action+Service | ✅ Advance 応用 | 得点率 *100 削除、Action 内の計算誤り + Service 集計まで波及 |
| B-A-03 | enrollment | Advance | `app/Services/TermJudgementService.php` | Service | ✅ Advance 応用 | whereIn 配列に `canceled` 混入、Service 層のクエリ条件誤り |
| B-B-01 | content-management | Basic | `app/Policies/PartPolicy.php` 他 6 Policy | Policy | ✅ Basic 階段 | Policy 層のコーチ判定削除、Basic 受講生が触れる純認可バグ |
| B-B-02 | content-management | Basic | `app/UseCases/Part/IndexAction.php` | Action | ⚠️ 階段方針なら要対応 | `->ordered()` 削除。Controller 内 inline 巻き戻しで 30 分 |
| B-B-03 | content-management | Basic | `app/UseCases/Learning/Show{Part,Chapter,Section}Action.php` | Action | ⚠️ 階段方針なら要対応 | 親資格 Published 判定削除、3 Action にまたがる。Controller 3 メソッドへの inline で 1〜1.5 時間 |
| B-B-04 | user-management | Basic | `app/UseCases/User/IndexAction.php` | Action | ⚠️ 階段方針なら要対応 | `withTrashed()` 常時呼出に書き換え、30 分。T-B-01 と同 Action 共有 |
| B-B-05 | user-management | Basic | `app/UseCases/Auth/IssueInvitationAction.php` | Action | ⚠️ 階段方針なら要対応 | 重複チェックガード削除、FormRequest 側に重複検査を移すと階段「Basic」適合。1 時間 |
| B-B-06 | auth | Basic | `app/UseCases/Auth/OnboardAction.php` | Action | ⚠️ 階段方針なら要対応 | 招待 accepted 化削除。Controller 内完結化なら 1.5 時間。B-B-12 と同 Action 共有 |
| B-B-07 | certification-management | Basic | `app/Http/Controllers/CertificationCategoryController.php::destroy()` | Controller | ✅ Basic 階段 | 1 行 `->with('success', ...)` 削除。階段方針完全適合の理想形 |
| B-B-08 | settings-profile | Basic | `app/Http/Controllers/Settings/ProfileController.php::update()` | Controller | ✅ Basic 階段 | リダイレクト先 URL 差し替え。階段方針完全適合 |
| B-B-09 | content-management | Basic | `app/Http/Controllers/BrowseController.php::show{Part,Chapter,Section}()` | Controller | ✅ Basic 階段 | `$this->authorize()` 呼出削除。階段方針完全適合(Policy 側はそのまま) |
| B-B-10 | meeting-quota | Basic | `app/UseCases/Meeting/CancelAction.php` (+ `RefundQuotaAction` 呼出削除) | Action | ⚠️ 階段方針なら要対応 | `RefundQuotaAction` 呼出 1 行削除。Controller 内完結化で 1〜1.5 時間 |
| B-B-11 | plan-management | Basic | `app/UseCases/Plan/IndexAction.php` | Action | ⚠️ 階段方針なら要対応 | status フィルタを `Draft` 固定に書き換え。Controller 内 inline で 30 分 |
| B-B-12 | auth | Basic | `app/UseCases/Auth/OnboardAction.php` | Action | ⚠️ 階段方針なら要対応 | `in_progress` 遷移削除、B-B-06 と同 Action 共有 |
| B-B-13 | auth | Basic | `app/Http/Requests/Auth/OnboardingRequest.php` | FormRequest | ✅ Basic 階段 | `confirmed` ルール削除。階段方針完全適合 |
| B-B-14 | chat | Basic | `app/Services/ChatUnreadCountService.php` | **Service** | ⚠️⚠️ Basic で最も逸脱 | 「送信者除外」条件削除、Service 層仕込み。Controller / View Composer に集計ロジック分散すると重複コードになる構造的問題あり。Advance 応用に格上げ or 模範解答 PJ 構造を抜本変更が必要 |
| B-B-15 | auth | Basic | `routes/web.php` + `EnsureUserRole` | Route+Middleware | ✅ Basic 階段 | `role:admin` を `role:admin,coach` に書き換え、Route 層仕込み |
| B-B-16 | auth | Basic | `app/Http/Middleware/EnsureActiveLearning.php` | Middleware | ✅ Basic 階段 | Middleware 内判定削除 |

### Task (7 件)

| ID | Feature | 難易度 | 対象ファイル | アーキ層 | 制約適合 | 備考・対応提案 |
|---|---|---|---|---|---|---|
| T-A-01 | mentoring | Advance | `app/Services/MeetingAvailabilityService.php::slotsForCertification` | Service | ✅ Advance 範囲 | N+1 解消、Service + 外部 API 呼び出し境界 |
| T-A-02 | mentoring | Advance | `app/Http/Controllers/MeetingController.php` → Action 分離 | Controller→Action 分離 | ✅ Advance 範囲 | Action 採用の Refactoring チケット |
| T-A-03 | mentoring | Advance | `app/Services/Google/{GoogleOAuthService, GoogleCalendarService}` 分離 | Service 分離 | ✅ Advance 範囲 | 外部 API Service 分離 |
| T-A-04 | 横断 | Advance | テスト追加(mentoring / ai-chat / meeting-quota の Service / Repository / Controller) | テスト | ✅ Advance 範囲 | Mockery + Http::fake + HMAC、外部 API テスト戦略 |
| T-B-01 | user-management | Basic | `app/UseCases/User/IndexAction.php` | Action | ⚠️ 階段方針なら要対応 | `->with('plan')` 追加。B-B-04 と同 Action 共有 |
| T-B-02 | mentoring | Basic | `app/UseCases/Dashboard/FetchCoachDashboardAction.php` | Action | ⚠️ 階段方針なら要対応 | `with()` + `withMax()` 追加、Controller 内完結化なら 2 時間 |
| T-B-03 | 横断(Schedule Command 群) | Basic | `app/Console/Commands/*Command` | Schedule Command (Console 層) | ✅ Basic 階段 | Schedule Command 自体は Action / Service を持たず chunkById で完結 |

## 横断整理

- **Basic 範囲内で完結できる仕込み層**(階段方針が完全に整合): Controller / FormRequest / Policy / Middleware / Route / Schedule Command
  - 該当: B-B-01, B-B-07, B-B-08, B-B-09, B-B-13, B-B-15, B-B-16 (Basic Bug 7 件) + T-B-03 (Basic Task 1 件)
- **Action 内仕込み**(階段方針なら要対応、現状は例外注記でカバー):
  - Basic Story 9 件全件 + Basic Bug 9 件 + Basic Task 2 件 = 計 **20 件**
- **Service 内仕込み**(階段方針なら最も逸脱、Basic で 1 件のみ):
  - Basic Bug 1 件 (B-B-14 ChatUnreadCountService)
- **Advance Bug の階段適合性**: 3 件すべて Action / Service 内仕込みで階段方針に整合済
