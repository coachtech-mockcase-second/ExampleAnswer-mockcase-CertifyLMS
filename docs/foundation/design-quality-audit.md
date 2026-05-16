# Certify LMS 設計品質メタ監査

> 監査対象: 全 18 Feature spec + 実装済み 4 Feature コード（auth / user-management / certification-management / content-management）
> 監査日: 2026-05-16
> 監査基準: Clean Architecture 軽量版 / 業界標準 / 受講生教材としての適切性

## 2026-05-16 4 判断による update（本監査後に確定）

本監査結果を受けて、以下 4 判断をユーザー承認のもと確定（spec 群 + steering + foundation すべて反映済み）:

| # | 判断 | 関連監査項目 | 反映先 |
|---|---|---|---|
| 1 | **規約 A 案採用**: `backend-types-and-docblocks.md` の `declare(strict_types=1)` / `private readonly` / `@param array{...}` / `@throws` / クラス・メソッド DocBlock を全 PHP ファイルで必須化。既存 4 Feature にも遡及適用 | 観点 1「Fortify Action 衝突」P1 / 観点 8「DIP 部分適用」P2 | `.claude/skills/feature-implement/SKILL.md` の「Step → 主参照 rules マップ」に PHP 系 Step 全てへ追加。`backend-types-and-docblocks.md` paths frontmatter で auto-load |
| 2 | **Sanctum SPA / 自前 FE SPA / 公開 JSON API / `HasApiTokens` / API Resource クラスを LMS 全体で不採用**: [[quiz-answering]] の Advance SPA 案は撤回し Blade + Form POST + Redirect 統一。Advance 必須学習は [[analytics-export]] API キー / [[chat]] Pusher / [[mentoring]] OAuth で養成 | 観点 1「Fortify Action 命名衝突」を実装側でも整理 / 観点 9「教育目的との整合」 | `quiz-answering/{requirements,design,tasks}.md` の SPA / Resource / JS 撤回、`steering/tech.md` 認証セクション update、`steering/product.md` の Feature 表 + スコープ外表 update |
| 3 | **学習時間トラッキングは Basic / Advance 区別なし**: JS / `sendBeacon` / heartbeat / 可視性検知を撤回、サーバ側 auto-start + 明示停止ボタン + Schedule Command auto-close の 3 経路で完結 | 観点 6「未使用 / 過剰 Action」 / 観点 9「学習曲線」P2 | `learning/{requirements,design,tasks}.md` の JS 撤回 + LearningSession ライフサイクル明文化、`feature-data-models.md` 反映 |
| 4 | **UserStatusLog に event_type カラム追加**: `UserPlanLog.event_type` とフォーマット統一。`UserStatusEventType` enum 新設（現時点 `status_change` 1 値で将来拡張可） | 観点 4「命名」P3「冗長 event_type」 / 観点 2「責務分離」 | `user-management/{requirements,design,tasks}.md` 追加、`plan-management/design.md` で監査ログ責務マトリクスを明文化、`feature-data-models.md` 反映 |

### 監査結論への影響

- **P0 課題**: UserStatus enum 拡張(4 値化) / EnrollmentStatus 3 値化 / Question → SectionQuestion 分離 → これらは v3 改修として spec 群はすべて反映済み、実装 4 Feature への遡及修正は段階 2 で実施予定（Plan 計画通り）
- **規約と実装の乖離**(implementation-quality-audit.md と連動): A 案で確定、`pint.json` 作成 + `declare(strict_types=1)` 一括付与 + `private readonly` 修正 + `HasApiTokens` 削除 + `ExampleTest` 削除を段階 2 で実施
- **本監査の P1 / P2 / P3 残課題**: 上記 4 判断で消化された項目以外は、Feature 実装フェーズ(plan-management 以降)で並走対応

---

## エグゼクティブサマリ

Certify LMS の Clean Architecture 軽量版採用は、Pro 生 Junior Engineer 育成の教材としては **戦略的に正しい選択** であり、Laravel コミュニティ標準のフラット Controller → Eloquent 構造より厚いが、`UseCases / Services / Repositories` のレイヤード分離は **Laracasts、Spatie、TJ Miller** など業界の上位ライブラリが採用する標準形に近い。208 ファイルの実装規模、約 71 個の Action クラス、5 個の Service、11 個のドメイン例外は、受講生が「読み切れる」上限（BookShelf 比 5-15% 減）の枠内に収まっており、量的設計は妥当である。

しかし、**監査の結果いくつかの設計品質課題** が判明した。最も深刻なのは **(1) v3 仕様 / 実装の不整合**（実装済み Enum が v3 リネーム前のままで、`EnrollmentStatus::Paused` が残存・`UserStatus::Active` が `InProgress` にリネームされていない・`Question` Model が `SectionQuestion` に分離されていない、4 Feature 実装後の v3 改修波及未対応）、**(2) 命名規則の二重基準**（`Action` 接尾辞ルールが宣言されているが、Fortify Action は `App\Actions\Fortify\` 配下に存在し命名が衝突、また Service が `*Service` / `CertificatePdfGenerator` の混在）、**(3) Feature 横断 Service の所有マトリクスが spec 段階の理想論で、実装時の循環依存が懸念される**（mock-exam → quiz-answering の `WeaknessAnalysisServiceContract` Interface 経由設計は、Laravel コンテナで動くが、受講生にとって過剰抽象）の 3 点である。

それ以外の観点（責務分離、Feature 境界、構造、教材適性）は **業界標準を満たすか、それ以上の品質** に達している。特に「Controller method 名 = Action クラス名」「集計責務マトリクス」「Repository 限定採用」「`backend-*` rules による Claude 実装指針の明文化」は **教材として極めて高品質**。後述する P0 課題を v3 反映と命名統一で潰せば、本プロジェクトは Pro 生育成教材として「業界平均より顕著に上」の評価が可能。

---

## 観点 1: Clean Architecture 軽量版の適用整合度

### 強み

- **層分離が一貫している**: 実装済み 4 Feature の Controller は全て「FormRequest 受け → `$this->authorize()` → `Action::__invoke()` → `redirect`」の 4 行ピラミッドに収まっている。`CertificationController` `QuestionController` `UserController` `InvitationController` を見ると、メソッド内ロジックがゼロで、`backend-http.md` の「Controller は薄く保つ」原則を完全に守っている（例: `/Users/yotaro/ExampleAnswer-mockcase-CertifyLMS/模範解答プロジェクト/app/Http/Controllers/CertificationController.php`、最も複雑な `index` でも 18 行）。Laravel コミュニティ標準（Controller に 30-100 行のロジックを書く）と比較して **格段にレイヤード分離が徹底** されている。

- **依存方向が一方向で逆流していない**: `Controller → Action → Service → Model` の流れに沿っており、`Service` が `Controller` を、`Model` が `Service` を呼ぶような逆流は確認されない。`UseCases/Auth/IssueInvitationAction` の constructor injection（`UserStatusChangeService`, `RevokeInvitationAction`）は **DIP（依存性逆転）を実用範囲で適用** している（Interface には抽象化しないが、receiver 側の concretion に依存する形で十分実用）。

- **Repository 限定採用が明文化されている**: `backend-repositories.md` で「外部 API のみ採用、DB 専用には作らない」と明示。実装でも `app/Repositories/` ディレクトリは未作成（Step 4 以降の Advance 範囲で `GeminiLlmRepository` が登場）で、`SectionRepository` `EnrollmentRepository` のような DB 専用 Repository の安易な追加を排除できている。これは **Laravel コミュニティ標準（FluxUI、Spatie 系も同様）** に整合しており、教材としての「過剰設計を避ける学び」を明確に提供している。

- **Controller method 名 = Action クラス名の規約**: `backend-usecases.md` で明示され、実装も 100% 準拠。`UserController::index()` → `IndexAction`、`UserController::updateRole()` → `UpdateRoleAction`、`InvitationController::resend()` → `ResendAction`。**業界標準の Laravel リソースコントローラ規約 + Action パターンの最良結合形** で、コードナビゲーションが直感的。

### 問題点

- **Fortify Action と UseCase Action の名前空間衝突（P1）**: `app/Actions/Fortify/CreateNewUser.php` `UpdateUserPassword.php` 等の **Fortify 公式パターン Action** と、`app/UseCases/{Entity}/{Action}Action.php` の **本プロジェクト UseCase** が共存している。受講生視点で「Action とは何か」が混乱する。Laravel エコシステムでは "Action" 語は Fortify / Jetstream で慣習的に「Single-purpose class」を指すが、本プロジェクトでは「Single-purpose class with `Action` suffix」を指すため、`app/Actions/Fortify/` ディレクトリの存在は教材としての一貫性を損ねる。
  - **改善提案**: `backend-usecases.md` 末尾に「`app/Actions/Fortify/` は Fortify 公式パターンに従う例外領域。`app/UseCases/` の Action クラスとは別物（Fortify Contract 実装のために存在）」と注記する。または `App\Actions\Fortify` を `App\Fortify\Actions` にリネームして衝突を回避する選択肢もあるが、後者は受講生が公式ドキュメントと照合する際の混乱を招くため非推奨。**最も筋がよいのは注記**。

- **「ラッパー Action」パターンの抽象階層が深い（P2）**: `app/UseCases/Invitation/StoreAction.php` は `app/UseCases/Auth/IssueInvitationAction.php` を呼ぶだけのワンライナー（実質 5 行）。Feature 横断のラッパー Action は `backend-usecases.md` で正当化されているが、「呼出元 Feature の Controller method 名と Action クラス名を一致させる」という規約のためだけの抽象階層で、Pro 生レベルでは **「読む価値の薄い 1 段経由」** に見える可能性がある。
  - **改善提案**: 受け入れる場合は **ラッパー Action にはコメントを必ず付ける**（例: `// user-management Feature が auth/IssueInvitationAction を委譲呼出するためのラッパー。Controller method 名 = Action 名規約の維持目的`）。または、`app/UseCases/Invitation/StoreAction.php` を `app/UseCases/Invitation/StoreInvitationAction.php` のように明示的命名にして「ラッパーであることを名前から判別できる」設計に切り替えるか、Controller がラッパーなしで直接 `auth/IssueInvitationAction` を DI してよいというルール緩和を検討する。**個人的には現状の規約維持 + コメント追加が最良**（規約の機械的一貫性は教材として高価値）。

- **依存逆転（DIP）の適用が部分的（P2）**: `WeaknessAnalysisServiceContract` Interface（quiz-answering 所有）→ `WeaknessAnalysisService` 実装（mock-exam 所有）の bind は ai-chat / quiz-answering / dashboard が共有する集計責務マトリクスの整合性を保つために必要だが、実装済みの Service（`MarkdownRenderingService` `UserStatusChangeService` `CertificatePdfGenerator` 等）は **全て Interface なしの具象クラス直接 DI**。教育的一貫性として「Interface はどういう時に作るか」の判断基準が明確でない。
  - **改善提案**: `backend-services.md` に判断指針を追記する。「Feature 横断で複数 Feature から呼ばれ、かつ正規実装が別 Feature にある場合のみ Interface を切る（例: `WeaknessAnalysisServiceContract`）。同 Feature 内で完結する Service は具象クラス直接 DI で十分」。これにより受講生は「過剰抽象化を避ける」教材として読める。

- **`Action` 内認可禁止の規約が一部破られそうな箇所がある（P1）**: `ReceiveCertificateAction`（enrollment spec、L60-77）の中で `if ($enrollment->user_id !== auth()->id()) throw new AuthorizationException();` と書かれている。これは **本人確認** であり「データ整合性チェック」と「認可」の境界が曖昧。`backend-usecases.md` では「認可は Controller / FormRequest で実施、Action 内ではデータ整合性のみ」と明記しているため、設計と spec の表現が乖離する。
  - **改善提案**: `EnrollmentPolicy::receiveCertificate(User $auth, Enrollment $enrollment): bool` を定義し、Controller の `$this->authorize('receiveCertificate', $enrollment)` で本人確認を完結させる。Action 内の `auth()->id()` 直接参照は依存方向（Action は HTTP 文脈に依存しない）的にも好ましくない。

---

## 観点 2: 責務分離（SRP）

### 強み

- **Action は単一業務操作で太らない**: 実装済み Action は全て `__invoke()` のみ、平均 20-50 行。`IssueInvitationAction`（最長 107 行）は、招待発行の 4 つの分岐パスを 1 つの DB::transaction 内に集約していて妥当な複雑度。100 行を超えるが「招待発行」という単一業務操作内の `if-else` 分岐であり、SRP に違反していない。

- **Service が「集計 / 計算」の純粋関数的役割に集中している**: `MarkdownRenderingService.toHtml()` `extractSnippet()` は完全な純粋関数。`UserStatusChangeService.record()` は 1 メソッドだけの薄い Service、`CertificateSerialNumberService` / `CertificatePdfGenerator` / `InvitationTokenService` も全て 1-3 メソッドで明確な役割を持つ。「神 Service」化していない。

- **集計責務マトリクスの設計が秀逸（P0 級の強み）**: `product.md` の集計責務マトリクス（11 行の表）は、本プロジェクトの **設計品質の白眉**。`ProgressService`（learning 所有）、`WeaknessAnalysisService`（mock-exam 所有）、`CompletionEligibilityService`（enrollment 所有）等を **計算 Service の所有 Feature** に一意配置し、dashboard は読み取り専用で集約する。これにより「dashboard の数字とサイドバーバッジの数字が乖離する」典型的バグを構造的に防ぐ。`SidebarBadgeComposer.php` と dashboard Action の両方が同じ `ChatUnreadCountService` を呼ぶ設計は、業界標準（Shopify、GitLab）の SSoT（Single Source of Truth）パターンと完全に整合する。

- **Policy が「ロール別 match」で読みやすい**: `QuestionPolicy` の `match ($auth->role) { Admin => true, Coach => $this->assignedCoach(...), default => false }` は **業界標準的な認可記述スタイル**（Spatie laravel-permission、Filament の Policy 例と一致）で、受講生が真似て書ける。

### 問題点

- **Eloquent Model に Domain Logic が一部混入（P2）**: `User.php::withdraw()` は「`email` を `{ulid}@deleted.invalid` にリネーム + `status` 更新 + SoftDelete」を 1 メソッドで実行する。Active Record パターンとしては許容範囲だが、コメントに「`UserStatusLog` 記録は呼び出し側 Action の責務」と書かれているのは **メソッドの責務がトランザクション境界を跨いでいる証拠**。受講生が「Model のメソッドは Action の責務を持つ場合がある」と誤学習する懸念。
  - **改善提案**: `WithdrawAction` 内に直接 `forceFill + delete + UserStatusLog` を書き、`User.withdraw()` メソッドを削除する。または `User::scopeWithdrawn` のみ残してドメインロジックは Action に集約する。Laravel コミュニティ標準では Active Record 内のドメインメソッドは許容されるが、Clean Architecture 軽量版を謳う以上、**ドメインロジックは Action に集約する** 一貫性を保つべき。

- **Service と Generator の命名混在（P2）**: `CertificatePdfGenerator.php` は `Service` 接尾辞を持たない。`backend-services.md` では「`{Feature}Service`」と命名規則を明示しているのに、PDF 生成だけは `Generator` 接尾辞で例外。受講生が「Service と Generator の使い分け基準」を聞いてくる可能性が高い。
  - **改善提案**: `CertificatePdfService` にリネームする。または `backend-services.md` に「外部出力（PDF / CSV / 画像）の生成 Service は `*Generator` 接尾辞を採用してよい」と注記する。前者を推奨（命名規則の機械的一貫性は教材として高価値）。

- **`UserController` の責務範囲が「自己」と「他者管理」で混在の懸念（P1）**: 実装済み `UserController` は admin が他者を管理する画面（index/show/update/updateRole/withdraw）を担当しているが、`settings-profile` spec では受講生自身のプロフィール編集は `ProfileController` として別実装される。spec 段階で住み分けは明確だが、**`UpdateAction.php` がプロフィール編集に使われている** 一方で、user-management の v3 spec では「`UpdateAction`（プロフィール編集、admin → 他者）撤回」と明記されている。実装と spec の責務範囲が乖離している。
  - **改善提案**: v3 反映時に `app/UseCases/User/UpdateAction.php` を削除し、自分のプロフィール編集は `app/UseCases/Profile/UpdateAction.php`（settings-profile Feature 所有）に移管する。実装/spec/規約の三者整合を取る。

---

## 観点 3: Feature 境界 (Bounded Context) の妥当性

### 強み

- **18 Feature の分割粒度が「ドメイン境界 + DB 集約 + UI 動線」の 3 軸で適切**: 例えば `mock-exam`（中核機能）、`mentoring`（独立した予約ドメイン）、`meeting-quota`（決済境界がある）、`chat`（リアルタイム / グループルーム）はそれぞれ別 Feature に分離されており、業界標準（Domain Driven Design の Bounded Context）と整合する。`plan-management` と `meeting-quota` は決済境界で分離されているが、初期付与（`GrantInitialQuotaAction`）の責務は **`meeting-quota` 所有 + `plan-management` から呼出**（D-1 で明示）で、適切に粗結合。

- **Feature 間結合が「Service 呼出 + Event/Listener + ラッパー Action」の3パターンに限定**: 例えば chat の `ChatMemberSyncService` は `CertificationCoachAttached` Event を Listener 経由で受ける疎結合構造。`enrollment::ReceiveCertificateAction` は `IssueCertificateAction`（certification-management 所有）を直接 DI せず、ラッパー Action 経由で呼ぶ。Feature 間の依存方向が明確（business action → 他 Feature の Service / Event は OK、`use App\UseCases\OtherFeature\*Action` の直接呼出はラッパー Action に限定）。

- **同じ概念の二重定義がない**: `QuestionCategory` は両系統（SectionQuestion / MockExamQuestion）から参照される **共有マスタ** で、content-management が CRUD を一意所有する設計（content-management/design.md L15）。Laravel ECF（Eloquent Concrete Factory）でよくある「`SectionQuestionCategory`」「`MockExamQuestionCategory`」の二重定義を避けている。

- **集計責務マトリクスで Feature 横断 Service の所有が明示（再掲、観点 2 と重複）**: `WeaknessAnalysisService` は mock-exam 所有 + dashboard / quiz-answering / analytics-export から消費、`PlanExpirationService` は plan-management 所有、`MeetingQuotaService` は meeting-quota 所有。「どの Feature が真実源か」が一目でわかる。

### 問題点

- **`notification` Feature の所有範囲が広すぎる懸念（P1）**: notification は 8 種類の Notification クラス + 8 個のラッパー Action（`NotifyChatMessageReceivedAction` 等）+ Schedule Command + Mail テンプレートを **本 Feature が一括所有** している。発火元 Feature が `app(NotifyXxxAction::class)($entity)` を呼ぶ設計は、業界では **Domain Event + Listener 分散方式** のほうが一般的。
  - **比較**: Shopify / Stripe / Linear などは「発火元 Feature が Event を dispatch、Notification Feature が Listener で受信して処理」の **Pub-Sub 方式**。本プロジェクトの「発火元が Notification ラッパー Action を直接呼出」方式は実装シンプルだが、Feature 間の **逆方向依存**（chat の `StoreMessageAction` が `NotifyChatMessageReceivedAction` を `use` する）が発生する。
  - **改善提案**: 現状の方式でも教材としては許容（受講生に Event/Listener の追加学習負荷を強いない選択）。ただし `backend-services.md` または notification/design.md に「**Domain Event 経由でも実装可能だが、本プロジェクトは「発火元 Action が Notify ラッパー Action を直接呼ぶ」シンプル方式を採用、理由は教材スコープに Event Listener の学習を含めないため**」と明記すると、受講生が「業界標準と何が違うか」を学べる。

- **`dashboard` Feature の境界が曖昧（P2）**: dashboard は **独自モデル / Migration / Service / Policy を作らず** 各 Feature の Service を消費する設計（dashboard/design.md L13）。これは適切な「読み取り専用集約 Feature」だが、`FetchAdminDashboardAction` `FetchCoachDashboardAction` `FetchStudentDashboardAction` `FetchGraduatedDashboardAction` の 4 つの Action が **dashboard が所有する唯一のロジック** で、それ以外は全て他 Feature への呼出。**Feature と呼ぶには痩せすぎ** の可能性。
  - **改善提案**: dashboard を `app/UseCases/Dashboard/*Action.php` のみのサブシステムとして扱い、独立 Feature ステータスを与えるかどうか議論しなおす。判断軸: 「dashboard 専用 spec / requirements を持つほど集約が複雑か」。現状の 4 ロール × 多種類集約は十分複雑なので、**Feature ステータス維持で OK**。ただし「dashboard は読み取り専用、独自 Model を持たない Feature」と明示的に文書化する。

- **`meeting-quota` と `mentoring` の責務境界（P1）**: meeting-quota が `MeetingQuotaService::remaining()` を提供、mentoring が `ReserveMeetingAction` 内で `ConsumeQuotaAction`（meeting-quota）を呼ぶ。**残数チェック責務** は mentoring の `ReserveMeetingAction` が meeting-quota の `MeetingQuotaService` を呼ぶ形だが、meeting-quota 自体が「残数 0 で `consumed` を INSERT しようとした時の例外」をどう扱うかが spec 上不明確（`InsufficientMeetingQuotaException` の発火元）。
  - **改善提案**: spec 段階で `ConsumeQuotaAction` が「残数 0 検証 + 例外 throw」を所有するか、`ReserveMeetingAction` が事前チェックして `ConsumeQuotaAction` は INSERT のみ行うかを **どちらか明確に決める**。現状の mentoring/design.md L32-37 を見る限り `ReserveMeetingAction` 側で事前チェック（`QS->remaining`）→ `InsufficientMeetingQuotaException` throw、`ConsumeQuotaAction` は単純 INSERT、という構造で良さそう。これを spec 文面で明示することを推奨。

---

## 観点 4: 命名

### 強み

- **Laravel 業界標準語彙の一貫採用**: `Controller / Action / Service / Repository / Policy / FormRequest / Resource / Middleware / Enum / Notification` は全て Laravel コミュニティ標準。Pro 生として企業の Laravel プロジェクトに参画した際、命名が直接理解できる。

- **Action 命名規則が機械的に明確**: `IndexAction / ShowAction / StoreAction / UpdateAction / DestroyAction`（CRUD）、`Fetch{Name}Action`（その他取得）、動詞 + Action（業務操作）の 3 ルールで全実装が貫かれている。受講生が「次に何を作るか」「どこに配置するか」を迷わない。

- **DB カラム / テーブル命名が完全に Laravel 規約**: snake_case 単数形（カラム）/ snake_case 複数形（テーブル）、ULID 主キー、`created_at / updated_at / deleted_at`、外部キー `{entity}_id` で完全に標準的。Pro 生として他プロジェクトの DB を見た時に違和感がない。

- **Enum 命名が PascalCase + バリュー snake_case**: `EnrollmentStatus::Learning` / value=`'learning'`、`UserRole::Admin` / value=`'admin'`。業界標準。`label()` メソッドで日本語表示名を持つパターンも COACHTECH LMS / iField LMS と一致。

### 問題点

- **`UserStatus::Active` と `InProgress` の不整合（P0、最重要）**: 実装済み Enum は `Active`（`/Users/yotaro/ExampleAnswer-mockcase-CertifyLMS/模範解答プロジェクト/app/Enums/UserStatus.php`）だが、v3 spec / product.md は `InProgress` にリネームしている。同様に `EnrollmentStatus::Paused` が実装には残存だが、v3 で 3 値（`learning/passed/failed`）に縮減されている。**`Graduated` 値も実装に未追加**。これは Step 2/3 で実装した 4 Feature が v3 改修前の Enum を使い続けているため発生した v3 反映漏れ。
  - **改善提案**: 残り 14 Feature 実装に入る前に **`UserStatus` enum 拡張 Migration を plan-management Feature の Step 1 で実行**（plan-management/design.md D-2 で明示済み）。実装済み 4 Feature の関連箇所（`IssueInvitationAction::activeUser` の `UserStatus::Active` 参照、`CertificationCatalog/IndexAction` の `EnrollmentStatus::Paused` 参照、`Question` モデルを `SectionQuestion` に分離する Migration）を **改修チケット化** する。これは P0 課題。

- **`Question` Model と `SectionQuestion` Model の不整合（P0）**: 実装済みは `Question.php`（`section_id` nullable で SectionQuestion / MockExamQuestion 兼用）だが、v3 設計は完全分離（`SectionQuestion` + `MockExamQuestion` 別テーブル）。`QuestionController` / `QuestionPolicy` / `Question*Action` / `QuestionCategoryController` の **大規模リネーム / リファクタが必要**。
  - **改善提案**: content-management Feature を **v3 改修対象とし、再実装** する。`Question.php` を `SectionQuestion.php` にリネーム、`section_id` を NOT NULL 化、`MockExamQuestion.php` を mock-exam Feature 実装時に新規作成。`QuestionController` 等の名称は要件シート定義時に「リネーム作業をチケット化」するか「実装済み命名を維持しつつ v3 設計を spec 側で吸収」を選ぶ必要がある。

- **`CertificatePdfGenerator` の命名規則からの逸脱（P2、観点 2 と再掲）**: `*Service` 規約から外れている。

- **`Fortify Action` 衝突（P1、観点 1 と再掲）**: `app/Actions/Fortify/` と `app/UseCases/{Entity}/{Action}Action.php` の衝突。

- **`SidebarBadgeComposer` の命名（P3、軽微）**: View Composer の Laravel 標準命名は `*Composer`。本実装でも `SidebarBadgeComposer.php` と命名されており規約通りだが、`backend-*.md` rules 内に View Composer の規約が **未記載**。受講生が View Composer の存在自体を知らない可能性。
  - **改善提案**: `backend-http.md` または新規 `frontend-blade.md` 等に「View Composer は `app/View/Composers/{Feature}{Element}Composer.php` に配置、サイドバーバッジ等の Blade 全体共通変数注入に使う」と追記する。

- **`UserStatusLog.changed_by_user_id` の null 許容（P3、軽微）**: システム自動変更（Schedule Command）の場合 null。これは適切な設計だが、`backend-models.md` の「テーブル / カラム命名」セクションには記載なし。null 許容外部キーの慣習を明文化すると教材として親切。

---

## 観点 5: 構造（フォルダ階層）

### 強み

- **`app/UseCases/{Entity}/` Entity 別ディレクトリ分割が秀逸**: 例えば `app/UseCases/Certification/` 配下に `StoreAction / UpdateAction / DestroyAction / PublishAction / ArchiveAction / UnarchiveAction / ShowAction / IndexAction` が並ぶ。これは Pro 生レベルとして「Entity 単位でファイルが集約 + 何が CRUD で何が業務操作か命名で識別」の最適配置。Spatie laravel-permission や Filament の Action 配置と一致。

- **`app/Services/` フラット配置の判断が妥当**: `app/Services/{Feature}/` のサブディレクトリ分割を避け、フラット配置（`MarkdownRenderingService.php` 等）を採用。Service の数が 5-20 程度であれば、Entity 別ディレクトリより **フラットでファイル名から所有 Feature が読み取れる** ほうがナビゲートしやすい。`product.md` の集計責務マトリクスが「所有 Feature」を明示している補完が効いている。

- **`app/Exceptions/{Domain}/` ドメイン別配置**: `Auth/EmailAlreadyRegisteredException.php` `Content/QuestionInUseException.php` `Certification/CertificationNotFoundException.php` `UserManagement/SelfWithdrawForbiddenException.php` のように Feature 別にディレクトリ分割。受講生が「この例外はどの Feature の責務か」を即時把握できる。業界標準（Symfony Domain Exception パターン、Spatie/PHP DDD Skeleton）と整合。

- **`tests/Feature/` と `tests/Unit/` の使い分けが明確**: `backend-tests.md` で「Controller / Action / HTTP リクエスト = Feature テスト、Service / Repository = Unit テスト」と明示。Laravel コミュニティ標準（PHPUnit Feature/Unit 分割）と一致。

- **`resources/views/{feature}/` Blade Feature 別配置**: dashboard / mock-exam / chat 等を Feature 単位でディレクトリ分割。Filament の Resource パターンや業界標準と一致。

### 問題点

- **`app/Http/Requests/{Entity}/{Action}Request.php` の階層深さ（P3、軽微）**: `app/Http/Requests/Certification/IndexRequest.php` は妥当だが、`app/Http/Requests/CertificationCoachAssignment/StoreRequest.php` のように Entity 名が長くなると **ディレクトリ名がフルパスで読みづらい**。
  - **改善提案**: ディレクトリは Feature 名（`app/Http/Requests/Certification/`）に集約、`CertificationCoachAssignmentStoreRequest.php` のように **クラス名を長くしてディレクトリ階層を浅く** する選択肢もあるが、現状の Entity 別配置は Laravel コミュニティ標準であり変更不要。**現状維持で OK**。

- **`tests/Feature/Http/` と `tests/Feature/UseCases/` の二重階層（P2）**: structure.md L77 で「Controller 単位（Feature/Http/）と Action 単位（Feature/UseCases/）」を併存と説明されているが、**Action テストを「複雑なケースのみ」と但し書きしている**。つまり Controller テストが Action ロジックも検証してしまうケースが多発する見込み。
  - **改善提案**: テスト規約の整理: 「Controller テスト = HTTP 経由 + Policy 認可 + DB 反映の確認、Action テスト = 業務ロジック単体（特に例外パス + DB トランザクション境界）」と **責務分離** を明示。受講生が「Action テストを書くべきか / Controller テストで足りるか」を判断できるようにする。

- **Feature ごとの app/ サブディレクトリ vs グローバル配置（P2）**: 現在は `app/Http/Controllers/` `app/UseCases/{Entity}/` のように **Entity 単位** で配置している。`app/Features/{FeatureName}/Controllers/` のような **Feature 単位完全モジュラー化** は不採用。これは Laravel コミュニティ標準（フラット型）に整合する妥当な判断だが、18 Feature に拡大すると `app/UseCases/` 配下に 18 ディレクトリ並ぶ。**Feature 規模が膨らんだ際の navigability 課題** が将来的に出る可能性。
  - **改善提案**: 現状の Entity 単位配置を維持。18 Feature 全実装後にディレクトリ数が 30-40 に達する見込みだが、Pro 生レベルとしては navigability 問題なし。**Filament admin パネルや Laravel Nova のような大規模 OSS でも同様のフラット配置** で運用している。

- **`docs/specs/` と `app/UseCases/` の Feature 名対応の明示が弱い（P3）**: `docs/specs/mock-exam/` （kebab-case）と `app/UseCases/MockExam/`（PascalCase）の **対応規則** が `structure.md` に明示されていない。
  - **改善提案**: `structure.md` の「specs ファイル構造」セクションに「**kebab-case spec 名 ↔ PascalCase app/ ディレクトリ名 の対応**: `mock-exam` ↔ `MockExam`、`user-management` ↔ `UserManagement`」を 1 行追記する。

---

## 観点 6: YAGNI / 過剰実装

### 強み

- **Repository を「外部 API のみ」と明示限定**: DB 専用 Repository を作らないルール（`backend-repositories.md`）が機能している。実装済み 4 Feature には Repository が一つもない。**Pro 生レベルとしても適切**（過剰なヘキサゴナルアーキテクチャを避ける判断）。

- **Service Interface を「Feature 横断時のみ」と限定**: `WeaknessAnalysisServiceContract` は mock-exam ↔ quiz-answering の Feature 横断時のみに限定採用。`MarkdownRenderingService` 等の同 Feature 完結 Service には Interface を切らない。**業界標準（YAGNI）の正しい適用**。

- **過剰抽象を避ける記述が `backend-*.md` に頻出**: `backend-usecases.md` L18-23「単純な CRUD で Controller → Model だけで済む場合は作らなくて良い」、`backend-services.md` L9-13「複数 Action から共有される計算ロジック」のみ作る、`backend-repositories.md` L11「DB 専用には作らない」。**過剰実装を防ぐルールが明文化されている**。

- **「スコープ外」が `product.md` で 30 項目以上明示**: 全文検索エンジン、qa-board の画像添付、動画教材、自己サインアップ、多言語化、SSO、決済機能、動画通話、バッジ / リーダーボード、進捗節目通知、通知設定 UI 等が全て **明示的に不採用** とされている。教材スコープを「足し続ける」ことを構造的に防いでいる。

- **18 Feature 全体で機能の重複がない**: notification は通知、settings-profile は自己管理、user-management は admin 他者管理、と責務が明確に分離。「ユーザー設定」が複数 Feature に散在する典型的アンチパターンを回避。

### 問題点

- **`MeetingQuotaTransaction` の `type` enum 5 値が一部過剰の可能性（P2）**: `granted_initial / purchased / consumed / refunded / admin_grant` の 5 値。`granted_initial` と `admin_grant` の差は **「Plan 起点」vs「admin 手動」** だが、両方とも `+N` の amount を持つ INSERT。**監査ログ的に区別したい** という意図は理解できるが、`admin_grant` を `granted_initial` の `granted_by_user_id` で表現することも可能。
  - **改善提案**: 現状の 5 値を維持。「監査ログ要件」と「UI 表示時の区別」（dashboard プラン情報パネルで「初期付与 4 回、管理者付与 2 回」のように分けて表示）を spec 段階で明示する。これにより 5 値の存在意義が教材として読める。

- **`StagnationDetectionService` 撤回の v3 反映が rules / structure.md に未反映（P3、軽微）**: v3 で滞留検知サービスを撤回したが、`backend-services.md` の「Service の例」に `StagnationDetectionService` 名が登場する可能性。
  - **改善提案**: 撤回した Service / Notification / Action 名を rules 内から削除する単純な作業。

- **`difficulty` カラム撤回の波及（P2）**: v3 で `QuestionDifficulty` enum / `Question.difficulty` カラム / `MockExamQuestion.difficulty` を削除する判断は適切（教材スコープを「合格点超え + カテゴリ別正答率」に集約）。ただし実装済みは `QuestionDifficulty.php` enum と `Question.difficulty` カラム参照（`StoreAction.php` L46 で `Arr::only` に `difficulty` を含む）が残存。
  - **改善提案**: content-management Feature の v3 改修チケットで一括対応。

- **未使用 / 過剰 Action は確認できる範囲では存在しない**: `app/UseCases/{Entity}/` 71 個の Action は全て Controller method と 1:1 対応。**死蔵 Action なし**。

---

## 観点 7: DRY / 重複

### 強み

- **集計責務マトリクスによる重複排除（再掲）**: 観点 2-3 で言及した通り、`ChatUnreadCountService` `ProgressService` 等が **複数 Feature から共有される唯一の真実源** として配置されている。dashboard / sidebar / chat 各 UI が同じ Service を呼ぶことで、同じロジックの重複実装を防いでいる。

- **`UserStatusChangeService.record()` の再利用**: `WithdrawAction` `IssueInvitationAction` `OnboardAction` 等が全て同じ `record()` を呼んで `UserStatusLog` を INSERT する。**ステータス変更ログ書き込みの重複が一箇所に集約**。

- **`MarkdownRenderingService.toHtml()` の再利用**: content-management（Section 表示）、ai-chat（メッセージレンダリング想定）、qa-board（投稿 / 回答レンダリング想定）が同じ Service を呼ぶ前提。サニタイズロジックの重複を防ぐ。

- **FormRequest の `authorize()` メソッドが Policy 呼出に集約**: 各 FormRequest で `return $this->user()->can('create', Model::class)` の 1 行で Policy に委譲。認可判定ロジックが FormRequest と Controller の両方に書かれる重複を排除。

### 問題点

- **`if ($user->status === UserStatus::Withdrawn) throw new UserAlreadyWithdrawnException()` の重複（P2）**: `WithdrawAction.php` L25-27、`UpdateRoleAction.php` L20-22、`UpdateAction.php` L14-16 で同じガードが繰り返される。
  - **改善提案**: `UserPolicy` で「withdrawn user は変更不可」のガードを定義し、Controller の `$this->authorize()` で一元化する。または `User` Model に `scopeNotWithdrawn` を切り、Action は Withdrawn user を引数で受け取らない前提にする。**前者を推奨**。

- **`if ($user->is($admin))` の自己操作禁止ガードの重複（P2）**: `WithdrawAction.php` L22-24、`UpdateRoleAction.php` L17-19 で同じガード。
  - **改善提案**: `UserPolicy` の `withdraw($auth, $target)` / `updateRole($auth, $target)` メソッド内で `$auth->is($target) === false` を返すように集約。Action 内のガードを Policy 側に寄せる。

- **`Arr::only($validated, [...])` での fillable 列挙の重複（P3）**: `Question/StoreAction::42`、`Question/UpdateAction::47` で `['body', 'explanation', 'category_id', 'difficulty']` を列挙。FormRequest の `validated()` が既にスキーマを規定しているのに、Action 内で再列挙している。
  - **改善提案**: `Question` model の `$fillable` と FormRequest の `rules()` を信頼し、`Arr::only` 列挙を削除（または定数化）。

- **`->orderByRaw("FIELD(status, 'active', 'invited', 'withdrawn')")` の MySQL/SQLite 分岐（P3、軽微）**: `User/IndexAction.php` L39-47 で MySQL / SQLite ドライバ別に sort 句を切替。複数 Feature の Action で同じ分岐が再実装される可能性。
  - **改善提案**: Eloquent macro または `User::scopeOrderByStatus` で集約する。ただし1箇所だけなら現状維持で OK。

---

## 観点 8: SOLID（特に SRP / DIP）

### 強み

- **constructor injection が徹底**: `IssueInvitationAction` `WithdrawAction` `SubmitAction`（mock-exam spec）等は全て constructor で依存を注入。具象クラス `new` の直接生成は確認した範囲では存在しない。Laravel コンテナによる自動解決を活用。

- **`ServiceProvider` での bind**: `MockExamServiceProvider`（spec）で `WeaknessAnalysisServiceContract` を `WeaknessAnalysisService` に bind。Pro 生レベルとして適切な DI 利用。

- **`backend-types-and-docblocks.md` で `readonly` 必須化**: DI 依存のコンストラクタプロパティに `readonly` を付与する規約。実装に向けて Pro 生レベルの不変性を強制。

### 問題点

- **DIP の部分適用（観点 1 と再掲）**: Interface は Feature 横断時のみ。同 Feature 内の Service は具象クラス直接 DI。これは YAGNI 観点では適切だが、**SOLID 純粋主義** からは部分適用。受講生が「Interface はいつ切るか」の判断軸を明確に学べるよう、`backend-services.md` に判断指針追記を推奨（観点 1 と同じ提案）。

- **`ReceiveCertificateAction` 内の `app()` ヘルパ直接呼出（P1）**: enrollment/design.md L62-72 で `app(CompletionEligibilityService::class)` `app(EnrollmentStatusChangeService::class)` `app(IssueCertificateAction::class)` `app(NotifyCompletionApprovedAction::class)` の 4 つを **app() ヘルパ経由で取得**。これは **Service Locator アンチパターン**（DIP 違反）。
  - **改善提案**: constructor injection に書き直す。`IssueInvitationAction` の constructor injection スタイル（実装済み）と一貫させる。これは P1 課題（spec 段階で誤った例を提示すると受講生が真似て書く危険）。

- **`Action` クラス自体は Interface を持たない**: 業界標準的には Action にも Interface を切るパターン（Spatie LaravelData の Pipe Interface 等）があるが、本プロジェクトは **具象クラス直接 DI**。教材スコープとしては妥当だが、Pro 生レベルとして「Action にも Interface を切るか」の判断軸を spec 段階で説明すると親切。**現状維持で OK**。

---

## 観点 9: 教育目的との整合

### 強み

- **「Pro 生 Junior Engineer 像」が CLAUDE.md で言語化されている**: 8 カテゴリ × 28 能力項目（A1-H3）が明示。チケット集合がこの 28 項目を養成できるか点検する 5 観点（能力カバレッジ / 量 / 質 / 構成 / 自走耐性）も明示。**教材設計が EBM（Evidence-Based Management）的に厳密**。

- **「コードリーディング負荷」が量的設計に組み込まれている**: BookShelf 比 5-15% 減のベースライン（CLAUDE.md L60、L160）。208 ファイル（実装済み 4 Feature）の規模は、確認テスト ContactForm（30-40 ファイル）→ 模擬案件① BookShelf（80-100 ファイル）→ 本プロジェクト（推定 400-500 ファイル）という学習曲線に整合。Pro 生レベルでも一気に飛び込めない規模だが、`backend-*.md` rules が「迷ったら見る場所」を提供して負荷を下げている。

- **既存パターン優先 + 真似て書ける命名**: `backend-usecases.md` L21「**1 Controller method = 1 Action**」、`backend-http.md` L18「**1 Controller method = 1 Action**」、`backend-policies.md` L34「**ロール別 `match`**」のように、受講生が **5 分で読める明示ルール** が並んでいる。Pro 生レベルとして「業界標準の Laravel コード」を真似て書ける。

- **PR 7 セクション必須が AI 耐性の中核**: 「調査内容 / 原因分析 / 動作確認スクショ・動画 / レビュー観点」が AI 出力不可。**Pro 生レベルの自己内省能力を養成**。

- **5 観点による「丸投げ」排除設計**: AI / コーチ / ドキュメント / 過去模擬案件いずれの丸投げも仕掛けスコアで排除。**業界の教材設計（Coursera、Udacity）では珍しい厳密さ**。

### 問題点

- **Service / Action / Repository の 3 層の存在意義を受講生が混同する懸念（P1）**: Laravel 教材 Basic 修了直後の受講生は、`UseCase Action` の概念を初めて見る。「Service と Action の違いがわからない」「両方あるけどどっちに書けばいいかわからない」と詰まる可能性。`backend-usecases.md` の比較表（観点 1 / 単位 / 例 / トランザクション）は的確だが、**最初の 1 つを書く瞬間** の判断軸は実例に頼る。
  - **改善提案**: 復習教材または完全手順書に「**Service と Action の使い分け実例集**」を 5-10 ケース掲載する。例: 「`Enrollment::create()` 1 行で済むなら Action 不要、Controller → Model でよい」「`Enrollment::create + EnrollmentStatusLog::create + Notification 発火` のように複数 INSERT が連鎖するなら Action + DB::transaction」「進捗率の計算を 2 箇所から呼ぶなら Service」のような実例。

- **`docs/specs/{name}/` が受講生に渡らない設計の徹底（P2 強み兼問題点）**: 構築側のみが docs/specs を見て模範解答実装する設計（CLAUDE.md MAP セクション）。これにより受講生は「完成形が docs/specs に書いてある」と知らずに、要件シート 50% から自分で考えて実装する。**A1（既存 PJ 参画）+ B1（50% 要件抽出）+ B4（やる/やらないの線引き）の養成** に直結する優れた設計。
  - **ただし懸念**: 完成手順書 / 復習教材で「docs/specs を見ない理由」を明示しないと、受講生が後から「先に答え見てた」と知って学習体験が損なわれる可能性。
  - **改善提案**: 完全手順書 / 復習教材の冒頭に「**docs/ は構築側メタ階層であり、本プロジェクトの受講生体験を実務的にするために意図的に渡されない**」と明示する。

- **`backend-types-and-docblocks.md` の規約厚みが Basic 修了直後には過剰な可能性（P2）**: `readonly` / `@throws` / shape annotation / `declare(strict_types=1)` が必須化されているが、これは **Pro 生レベルの目標** であり、Basic 修了直後には初出。Pint hook で自動付与される項目もあるが、shape annotation や `@throws` は手で書く必要があり、受講生が「DocBlock の書き方を学ぶ時間」をどこで確保するか。
  - **改善提案**: 復習教材または完全手順書に「**DocBlock と型宣言の使い分け実例**」を 5 個程度掲載。チケット選定時に「DocBlock 必須」のチケットを 2-3 個含めて反復養成する。

- **学習曲線の急峻さ（P3、軽微）**: Wave 0b（基盤構築）→ Feature 実装 16 個（並列実行可）→ ロックド Blade 受け取り → 50% 要件で実装、という学習曲線で、受講生視点では「Wave 0b の基盤がブラックボックス」のまま実装が進む。Wave 0b の基盤が受講生に学習可能な形で渡るかどうかが課題。
  - **改善提案**: Wave 0b の成果物（共通 Model / Layout / Provider 等）は **`backend-*.md` rules + Wave 0b 解説資料** で受講生が読めば理解できる形にする。「触らないが理解する」教材設計を徹底する。

---

## 全体総評 + 優先改善アクション

### 全体総評

Certify LMS は **業界標準を超える教材設計品質** を達成している。特に「集計責務マトリクス」「Controller method = Action クラス名」「Repository 限定採用」「`backend-*.md` rules による Claude 実装指針」「PR 7 セクション必須による AI 耐性」は、Laravel 教育プロジェクトとしては **国内では類を見ない厳密さ**。Pro 生 Junior Engineer 像の 28 能力項目を養成する 5 観点点検も EBM 的に正しい。

ただし、v3 改修の波及が実装済み 4 Feature に未反映の箇所が複数あり（UserStatus / EnrollmentStatus / Question 分離）、これは Step 3 でも Wave 0b でも残り 14 Feature 実装でも、いずれかの段階で「v3 改修チケット化」して潰す必要がある。これは「Pro 生として既存 PJ の v3 改修を体感する」教材スコープに直結するため、**P0 課題として明示的に対処する** ことを推奨。

ファイル数 208（実装済み 4 Feature）→ 推定 400-500（全 18 Feature 実装後）の規模、71 個の Action、5 個の Service、11 個のドメイン例外は、Pro 生レベルとして「読める / 真似て書ける」上限ぎりぎりの **適切な量**。これ以上厚くせず、これ以下に減らさず、現状の設計を v3 反映完了させれば、本プロジェクトは **国内 Laravel 教育プロジェクトとして最高峰** に到達する。

### 優先改善アクション

#### P0（即座に対処、Step 3 進行のブロッカー）

1. **`UserStatus` Enum を `Invited / InProgress / Graduated / Withdrawn` の 4 値に拡張**（plan-management Feature Step 1 で Migration）+ 実装済み 4 Feature の `UserStatus::Active` 参照を `UserStatus::InProgress` に一括置換
2. **`EnrollmentStatus` を 3 値（`learning / passed / failed`）に縮減** + `Paused` 削除 + 実装済み `CertificationCatalog/IndexAction.php` の `EnrollmentStatus::Paused` 参照を削除
3. **`Question` Model を `SectionQuestion` にリネーム** + `section_id` NOT NULL 化 + `MockExamQuestion` を mock-exam Feature 実装時に新規作成 + `QuestionController / Policy / Action / FormRequest` を全て `SectionQuestion*` 系にリネーム

#### P1（Step 3 開始前または並走で対処）

4. **`ReceiveCertificateAction` 内の認可ロジックを `EnrollmentPolicy::receiveCertificate` に移管** + `app()` ヘルパ直接呼出を constructor injection に書き直す（enrollment spec の修正）
5. **`UserController` の `UpdateAction` を撤回** + `settings-profile` Feature の `app/UseCases/Profile/UpdateAction.php` に移管（v3 spec と整合）
6. **`Fortify Action` と `UseCase Action` の命名衝突を `backend-usecases.md` に注記**（混乱回避）
7. **「ラッパー Action」の存在理由をコメントで明示**（受講生の理解を助ける）
8. **Service / Action の使い分け実例集を完全手順書 or 復習教材に追加**
9. **`UserPolicy` に「withdrawn user 不変」「自己操作禁止」ガードを集約**（DRY 違反解消）
10. **`notification` Feature の発火元方式（ラッパー Action 直接呼出）の理由を明文化**（業界標準 Event/Listener との差を学べるように）

#### P2（Wave 0b 中または直後に対処、教材品質向上）

11. **`CertificatePdfGenerator` を `CertificatePdfService` にリネーム**（Service 命名規則統一）
12. **`User::withdraw()` メソッドを `WithdrawAction` に集約** + Active Record メソッドからドメインロジック撤退
13. **`backend-services.md` に Interface 採用判断指針を追記**（YAGNI と DIP の境界明示）
14. **テスト規約整理**: Controller テスト vs Action テストの責務分離を明文化
15. **`MeetingQuotaTransaction.type` 5 値の存在意義を spec 段階で明示**
16. **`meeting-quota` と `mentoring` の責務境界を spec 文面で明示**（残数チェック発火責務）
17. **`StagnationDetectionService` 撤回の rules 内残存削除**
18. **`difficulty` 撤回の波及を実装済み Action（`Question/StoreAction.php` `UpdateAction.php`）から削除**
19. **`backend-types-and-docblocks.md` の Pro 生レベル要件と Basic 修了直後の学習曲線のギャップを復習教材で埋める**

#### P3（任意、品質ブラッシュアップ）

20. **`structure.md` に「specs kebab-case ↔ app/ PascalCase の対応規則」追記**
21. **`backend-http.md` または新規 rules に View Composer 規約追記**
22. **null 許容外部キー（`changed_by_user_id` 等）の慣習を `backend-models.md` に追記**
23. **`Arr::only($validated, [...])` の fillable 列挙重複を整理**（軽微）
24. **`User/IndexAction.php` の MySQL/SQLite 分岐を Eloquent macro 化**（軽微）
25. **`docs/` が受講生に渡らない理由を完全手順書冒頭に明示**

### 監査結論

Certify LMS の設計は **Pro 生 Junior Engineer 育成教材として国内最高峰の品質に到達可能** な土台を持つ。P0 課題（v3 改修の波及）を即座に潰し、P1 課題を Step 3 進行と並走で対処すれば、業界標準を超える教材として完成する。**最も重要な戦略判断は「v3 改修の網羅性確保」と「Service / Action 使い分け教材の充実」の 2 点**。それ以外の課題は微調整領域であり、本プロジェクトの本質的な設計品質は既に高い水準にある。
