# Certify LMS 100% 要件シート

## 位置づけ

**100% 版要件シート本体**(採点者・コーチ向け、ただし両者は別ロール)。1 チケット = 1 .md ファイルとして管理し、本シートを正として **評価シート** と **30% 版要件シート** を派生生成する(本シートが Single Source of Truth)。

| ロール | 責務 | 本シートでの主な参照箇所 |
|---|---|---|
| **採点者** | PR を確認して受け入れ条件を判定 → 評価シートに採点を記入 | 各チケットの **受け入れ条件** セクション |
| **コーチ**(PM 役) | 受講生からのヒアリングに応答(30% 版から 100% 版相当の情報を引き出す手助け) | 各チケット下半分の **実装方針** + **補足** セクション |

| 役割 | 場所 |
|---|---|
| 議論ログ(40 件草案 fix 済、2026-05-21) | `../要件ブレインストーミング.md` |
| 各チケット定義(本ディレクトリ) | `Story/` `Bug/` `Task/` 配下 |
| 詳細化作業ログ(模範解答 PJ 懐疑的レビュー) | `_review-log.md`(Phase D で活用) |
| 詳細化規約 | `../../.claude/rules/ticket-spec.md`(paths で auto-load) |
| チケット作成 Skill | `../../.claude/skills/ticket-detail-100p/`(テンプレ含む) |
| 評価シート(派生) | `../評価シート.md`(Phase E で本シートから派生) |
| 受講生配布形 30% 版(派生) | `../要件シート_詳細度30%/`(Phase F で本シートから変換生成) |

## ディレクトリ構造

```
要件シート_詳細度100%/
├── README.md              # 本ファイル(ナビ + チケット一覧 + 進捗トラッカー)
├── _review-log.md         # 詳細化中の模範解答 PJ 懐疑的レビューを集約
├── 01_概要.md             # ターム内容 + 開発プロセス + 環境構築手順(後工程で別途作成)
├── Story/                 # 15 件(Basic 10 / Advance 5)
├── Bug/                   # 19 件(Basic 16 / Advance 3)
└── Task/                  # 7 件(Basic 3 / Advance 4)
```

**合計 41 件 / 目標 225h ± 10%**。Basic / Advance はファイル名(`S-B-XX` / `S-A-XX` 等)で識別する(サブディレクトリで階層化しない)。

> テンプレ(`story.md` / `bug.md` / `task.md`)は `.claude/skills/ticket-detail-100p/templates/` 配下。本ディレクトリには配置しない。

## ファイル命名規則

`{チケットID}_{Feature略称-連番}.md`(例: `S-B-01_qa-board-01.md` / `B-B-09_content-management-04.md`)。`B` = Basic / `A` = Advance。

## 採点者の使い方

**採点者** が本シートで各チケットを評価する標準フロー:

1. 該当チケットの **PR を確認**(file Changes で実装内容、画面動作で振る舞い)
2. 本シート対象チケット(`Story/{ID}.md` 等)の **受け入れ条件** をチェックリスト形式で 1 項目ずつ判定
3. 受け入れ条件 = 評価シート ① の 1 採点行 と 1:1 対応(採点シートに転記)
4. 横断採点(② コード品質 / ③ ドキュメント)は評価シート側で行う(チケット単位の作業ではない)

> 受講生からのヒアリング応答は **コーチ** の役割(本シートの **実装方針** + **補足** セクションを参照)。採点者は通常これらのセクションを見ない。

## 評価シートとの対応関係

本シートは評価シートの **直接の入力源**。対応関係:

| 評価シート大項目 | 中身 | 本シートとの関係 |
|---|---|---|
| ① チケット完了(≒65%) | 各チケットの受け入れ条件を採点行に展開 | 各 .md の **受け入れ条件** が 1 項目 = 1 採点行 |
| ② 横断コード品質(≒20%) | Pint / 命名 / N+1 / Clean Architecture / 型宣言 / テスト品質 | 個別チケットではなく全コードベース横断 |
| ③ 横断ドキュメント(≒15%) | README / ONBOARDING 改修 / 全 PR の 7 セクション記述率 / カバレッジ達成率 / 動的機能 PR の動画率 | 個別チケットではなく全 PR 横断 |

「テスト pass」「PR 7 セクション完備」「動画記述」は **チケット個別の受け入れ条件には書かない**(全チケット共通の横断採点として ③ に集約)。

## 詳細化規約

詳細化中の判断基準(受け入れ条件原則 / 振る舞いと文言の分離 / Basic 制約 / ロール表記 / 採点必要レイヤー区分 / Bug 修正範囲 / 依存記録方向 / 模範解答 PJ 懐疑的レビュー)は **`../../.claude/rules/ticket-spec.md`** に集約(paths で本ディレクトリ作業時 auto-load)。

## チケット詳細化 Skill

`/ticket-detail-100p {チケットID}` で詳細化(Skill: `../../.claude/skills/ticket-detail-100p/`)。複数チケットの並列生成にも対応(単一セッション連続 or worktree-spawn 連携)。詳細は `SKILL.md` 参照。

## 進行順

**全 Basic → 全 Advance / Story → Bug → Task**:

```
Story Basic (9) → Bug Basic (16) → Task Basic (3) → ★ Basic 完成
→ Story Advance (5) → Bug Advance (3) → Task Advance (4) → ★ 全件完成
```

- **Basic → Advance**: Advance は Basic 上に積む拡張(例: S-A-05 Sanctum は S-B-05 認証なし API の上に後付け)。Basic 確定後に Advance を詳細化するほうが整合が取りやすい
- **Story → Bug → Task**: 記述スタイルが異なる(特に Bug は実装方針なし)ため、種別単位で進める

## チケット一覧(40 件)

> 出所: `../要件ブレインストーミング.md` § 2(2026-05-21 草案 fix)。Skill 実行時の入力情報源として使用。

### Story Basic(10 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `S-B-01` | `qa-board-01` | 質問掲示板の実装 | 新規機能の構築 | 13h |
| `S-B-02` | `meeting-quota-01` | 面談パックマスタ管理(admin マスタ CRUD) | 既存機能の拡張 | 6h |
| `S-B-03` | `plan-management-01` | プラン管理 Admin マスタ UI | 既存機能の拡張 | 6h |
| `S-B-04` | `notification-01` | 通知(Laravel Notification、DB + Mail) | 新規機能の構築 | 12h |
| `S-B-05` | `notification-02` | 通知 JSON API(認証なし) | 新規機能の構築 | 6h |
| `S-B-06` | `enrollment-03` | 個人目標(EnrollmentGoal)CRUD | 新規機能の構築 | 8h |
| `S-B-07` | `settings-profile-01` | 設定・プロフィール画面 | 既存機能の拡張 | 7h |
| `S-B-08` | `mentoring-06` | コーチ用 受講生メモ(EnrollmentNote)編集 | 新規機能の構築 | 6h |
| `S-B-09` | `notification-05` | admin お知らせ配信機能 | 新規機能の構築 | 8h |
| `S-B-10` | `notification-04` | 面談リマインダー通知(前日 + 1 時間前) | 新規機能の構築 | 5h |

### Story Advance(5 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `S-A-01` | `mentoring-01` | Google Calendar 連携(面談予約) | 既存機能の拡張 | 16.5h |
| `S-A-02` | `ai-chat-01` | Gemini AI チャットボット | 新規機能の構築 | 12h |
| `S-A-03` | `meeting-quota-02` | Stripe 連携(追加面談購入) | 新規機能の構築 | 16h |
| `S-A-04` | `certification-management-01` | 修了証 PDF 出力 | 既存機能の拡張 | 6h |
| `S-A-05` | `notification-03` | Sanctum Cookie 認証追加 + JS フロント通知表示 | 既存機能の拡張 | 13.5h |

### Bug Basic(16 件)

> **Step 4 仕込み方の前提**: 模範解答 PJ は `backend-http.md` の規約(Controller は薄く / メソッド内ビジネスロジック原則 0 行 / 1 Controller method = 1 Action)に従って、ビジネスロジックを Action / Service / FormRequest / Policy / Middleware に分散している。「Step 4 仕込み方」列は **模範解答 PJ の実装レイヤー基準** で記述し、Step 4 引き算実装時の指示書として正確な仕込み箇所を示す。

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 | Step 4 仕込み方 |
|---|---|---|---|---|---|
| `B-B-01` | `content-management-01` | コーチ教材アクセス 403 → 編集可能化 | 認可・認証 | 4h | コーチ Policy で `denied` を返す |
| `B-B-02` | `content-management-02` | 教材一覧 ソート順無視 | 機能(ソート) | 2.5h | `Part\IndexAction` の `orderBy('order')` 追加忘れ |
| `B-B-03` | `content-management-03` | 受講生向け教材閲覧で archived 資格の教材が露出 | データ(絞り込み漏れ) | 3h | 模範解答 PJ の `Learning\Show{Part,Chapter,Section}Action` に「親資格が公開中(`CertificationStatus::Published`)でなければ 404」ガードを追加実装済(2026-05-24、`BrowseControllerTest` に archived 404 ケース 3 件追加 → 全通過) → Step 4 でこのガード 3 箇所を削除し、archive された資格の教材が受講登録済み受講生に閲覧可能になる。※ Action 内のため Basic 範囲外注記あり |
| `B-B-04` | `user-management-02` | admin 受講生一覧で withdrawn も表示 | データ(クエリ漏れ) | 3h | `User\IndexAction` の `if ($status === UserStatus::Withdrawn) { $query->withTrashed(); }` 条件を取り払って `$query->withTrashed();` を冒頭で常時呼ぶ(通常一覧にも SoftDelete 済 withdrawn ユーザーが混入) |
| `B-B-05` | `user-management-03` | ユーザー招待で重複 email 可能 | データ(バリデーション) | 2.5h | `Auth\IssueInvitationAction` の `EmailAlreadyRegistered` ガード(既存 in_progress / graduated user との email 重複チェック + 例外 throw)ブロックを削除(FormRequest に `unique` は元々存在しないため、Action ガード側を抜く) |
| `B-B-06` | `auth-01` | 招待トークン使い回し可能 | セキュリティ(トークン期限管理) | 3h | `Auth\OnboardAction` の `$invitation->forceFill(['status' => InvitationStatus::Accepted, 'accepted_at' => $now])->save();` を削除(使用済化忘れ → status が Pending のまま → 同じトークンで何度でも再オンボード可能) |
| `B-B-07` | `certification-management-02` | 資格分類マスタ削除後のフラッシュメッセージ表示忘れ(admin) | UI/UX(フラッシュ) | 2h | `CertificationCategoryController::destroy()` の `redirect()->route('admin.certification-categories.index')->with('success', '分類を削除しました。')` から `with('success', ...)` のみ削除(store / update は正常維持で destroy のみ漏れのコピペミス) |
| `B-B-08` | `settings-profile-02` | 設定保存後のリダイレクト先誤り | UI/UX(リダイレクト) | 2h | Controller の `redirect()` 先誤り |
| `B-B-09` | `content-management-04` | 受講生が未登録資格の教材詳細を直叩き閲覧可 | 認可・認証(IDOR) | 3h | 受講生向け教材閲覧 `Learning\BrowseController` の `showSection()` / `showPart()` / `showChapter()` / `showEnrollment()` から `$this->authorize('learning.section.view', ...)` 等の呼び出しを削除(対象は learning Feature であり、content-management の admin 系教材管理ではない) |
| `B-B-10` | `meeting-quota-04` | 面談キャンセル時に残数が返却されない | データ(Tx 漏れ) | 3h | `Meeting\CancelAction` 内で `MeetingQuotaTransaction.refunded` INSERT 漏れ(※Basic 範囲外、Skill 生成時に「※」注記) |
| `B-B-11` | `plan-management-02` | admin プラン管理一覧の status フィルタコピペミス | データ(クエリ条件誤り) | 3h | `Plan\IndexAction`(または `PlanController::index`)の status フィルタ条件で、`PlanStatus::Published` を指定したのに `where('status', PlanStatus::Draft->value)` を書いてしまう enum 値コピペミス(公開中プランが draft 扱いで非表示)。S-B-03(プラン管理 Admin UI)直後の連続学習として配置 |
| `B-B-12` | `auth-02` | オンボーディング完了時の status 遷移漏れ | 機能(状態遷移) | 3h | `Auth\OnboardAction` の `$user->update(['status' => UserStatus::InProgress])` 行を削除 |
| `B-B-13` | `auth-03` | オンボーディングで `password_confirmation` 漏れ | データ(バリデーション) | 2h | FormRequest の `'password' => 'confirmed'` ルールを抜く |
| `B-B-14` | `chat-01` | chat 未読バッジで自分の発言もカウント | データ(クエリ条件誤り) | 3h | `ChatUnreadCountService` の未読集計 query で `where('sender_id', '!=', auth()->id())` 漏れ |
| `B-B-15` | `auth-04` | コーチが管理者専用画面にアクセス可(ロールガード漏れ) | 認可・認証(Middleware チェック漏れ) | 3h | admin 専用ルート群(`routes/web.php`)の `role:admin` を `role:admin,coach` に書き換え(隣接する共有ルート群からのコピペミスを模す → コーチが管理者専用エリアに混入、受講生は引き続き 403)。**旧案「withdrawn ログイン可能」から差し替え**(2026-05-24): 退会ユーザーは必ず SoftDelete されログインクエリから除外されるため、status チェックを消しても観測不能 → 観測可能な role ガード漏れに変更 |
| `B-B-16` | `auth-05` | graduated ユーザーがプラン機能アクセス可 | 認可・認証(Middleware チェック漏れ) | 3h | 既存 `EnsureActiveLearning` Middleware から status 判定行を削除 |

### Bug Advance(3 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 | Step 4 仕込み方 |
|---|---|---|---|---|---|
| `B-A-01` | `mentoring-02` | 面談予約時の悲観ロック漏れによる面談回数二重消費 | 並行性 | 6h | `MeetingQuota\ConsumeQuotaAction` の `User::query()->whereKey($user->id)->lockForUpdate()->first();` 行を削除(同時 2 リクエストで残数チェック → INSERT の間に TOCTOU が発生し、残数 1 件のユーザーが 2 件予約成立 → `MeetingQuotaTransaction` が 2 件 INSERT され残数 -1 の会計バグ) |
| `B-A-02` | `mock-exam-04` | 模試採点ロジックバグ | 機能(計算) | 8h | `MockExamSession\GradeAction` の `$scorePercentage = round($totalCorrect / $totalQuestions * 100, 2)` から `* 100` を削除(75% のはずが 0.75% として保存され、合否判定 `>= passing_score_snapshot` で全員不合格扱い)。クラス名は `ScoringAction` / `ScoringService` ではなく `MockExamSession\GradeAction` |
| `B-A-03` | `enrollment-04` | ターム判定ロジックの誤り | 機能(計算 + クエリ) | 4h | `TermJudgementService` の `status IN (...)` に `canceled` を含めるミス |

### Task Basic(3 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `T-B-01` | `user-management-01` | Admin ユーザー管理画面の N+1 解消 | パフォーマンス | 3.5h |
| `T-B-02` | `mentoring-05` | コーチダッシュボードの担当受講生一覧 N+1 解消 | パフォーマンス | 4h |
| `T-B-03` | `horizontal-01` | `chunk()` / `chunkById()` / `cursor()` でメモリ最適化 | パフォーマンス | 5.5h |

### Task Advance(4 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `T-A-01` | `mentoring-03` | 面談予約画面の N+1 解消 | パフォーマンス | 6h |
| `T-A-02` | `mentoring-07` | mentoring の Controller method を Action 分離 | リファクタリング | 4h |
| `T-A-03` | `mentoring-08` | Google Calendar 外部連携を Service 分離 | リファクタリング | 4h |
| `T-A-04` | `horizontal-04` | モックを用いたテスト追加(外部 API 連携) | リファクタリング | 5.5h |

## 進捗トラッカー

| 種別 | Basic | Advance | 計 |
|---|---:|---:|---:|
| Story | 10 / 10 | 5 / 5 | 15 / 15 |
| Bug | 16 / 16 | 3 / 3 | 19 / 19 |
| Task | 3 / 3 | 4 / 4 | 7 / 7 |
| **計** | **29 / 29** | **12 / 12** | **41 / 41** |

### 完成済み

- ✅ `S-B-01 / qa-board-01` 質問掲示板の実装(2026-05-23 サイドバーバッジ機能廃止 / 通知発火スコープ外 [`S-B-04` に移管] で書き直し → 2026-05-27 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成**、AC 10 件、Basic 範囲で Controller 内完結を前提に記述、実装方針を「インターフェース(認可列含む)→ データモデル(制約列含む)→ コンポーネント(クラス名 + ファイルパス集約)→ 異常系 → 設計判断(テスト観点内包)」の外部→内部潜行順 5 構造に再編、依存チケットなし)
- ✅ `S-B-02 / meeting-quota-01` 面談パックマスタ管理(admin マスタ CRUD)(2026-05-23 詳細化、Basic 範囲で Controller 内完結を前提に記述、状態遷移 3 種(publish / archive / unarchive)+ 公開中削除ガード(409)、依存チケットなし)
- ✅ `S-B-03 / plan-management-01` プラン管理 Admin マスタ UI(2026-05-25 詳細化、Basic 範囲で Controller 内完結を前提に記述、削除は物理削除 = 下書き × 受講者なしのみ可 + 状態遷移 3 種、🕐 例外クラスのメッセージ責務は Phase D で MeetingPack と統一、Step 4 引き算は B-B-11 と重複あり Phase D 課題 #3 で整合確認)
- ✅ `S-B-04 / notification-01` 通知基盤(Laravel Notification、DB + Mail)(2026-05-25 詳細化 → 2026-05-26 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成**、AC 10 件、4 通知種別[chat / Q&A / 面談予約 / 面談キャンセル]+ サイドバー通知一覧 / 行クリック既読化 + 全件既読化、管理者は受信対象から除外、リマインダー / Announcement / API / Pusher / Sanctum は別チケット、依存チケットなし)
- ✅ `S-B-05 / notification-02` 通知 JSON API(認証なし)(2026-05-25 詳細化 → 2026-05-26 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成**、AC 8 件、Basic 範囲で過去案件の公開 API パターン踏襲、`api.v1.notifications.*` ルート 3 本 [index / markAsRead / markAllAsRead] + Resource + Api FormRequest + 対象ユーザークエリ指定、認可なしの構造的脆弱性は `S-A-05` で Sanctum 認証後付けにより実用化、Step 4 = 模範解答 PJ の `auth:sanctum` 取り外し + 対象ユーザー解決方式の切替、依存 `S-B-04`)
- ✅ `S-B-06 / enrollment-03` 個人目標(EnrollmentGoal)CRUD(2026-05-25 詳細化、Basic 範囲で Controller 内完結を前提に記述、新規テーブル `enrollment_goals` + Policy 責務委譲(`view` を `EnrollmentPolicy::view` に委譲)+ 達成マーク / 達成解除のべき等性 + 編集は専用ページ + 削除は HTML confirm、コーチ / 管理者は閲覧専用、依存チケットなし)
- ✅ `S-B-07 / settings-profile-01` 設定・プロフィール画面(2026-05-25 詳細化、Basic 範囲で全ロール共通の 2 タブ[プロフィール / パスワード]+ アバター変更 + コーチ専用固定面談 URL 編集、Fortify Password Update 活用、`UserPolicy::updateSelf` 新設、`EnsureActiveLearning` Middleware 不適用で graduated もアクセス可、面談設定タブ / Google Calendar 連携は `S-A-01` で扱う、🕐 B-B-08 と Step 4 重複あり Phase D 課題 #4 で整合確認)
- ✅ `S-B-08 / mentoring-06` コーチ用 受講生メモ(EnrollmentNote)編集(2026-05-25 詳細化、Basic 範囲で Controller 内完結を前提に記述、新規テーブル `enrollment_notes` + Policy 二重判定[担当コーチ + 作成者本人 + 管理者越境] + 受講生は閲覧含め完全 403 + 編集時の作成者(`coach_user_id`)不変性、`EnrollmentGoal`(S-B-06)と役割対称ペア、依存チケットなし)
- ✅ `S-B-09 / notification-05` admin お知らせ配信機能(2026-05-25 詳細化 → 2026-05-26 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成**、AC 11 件、新規お知らせエンティティ + 配信対象タイプ 3 種[全受講生 / 資格指定 / ユーザー指定]+ `S-B-04` 通知基盤利用 + 業務トランザクション確定後の配信発火 + 受講中フィルタ + 配信不可逆(再配信 / 編集 / 取消なし)、依存 `S-B-04`)
- ✅ `S-B-10 / notification-04` 面談リマインダー通知(前日 + 1 時間前)(2026-05-27 新規作成、Basic 範囲で Schedule Command + 同期発火 + Queue なし、`notifications:send-meeting-reminders --window={eve\|one_hour_before}` の 2 タイミング配信 + 当事者全員[受講生 + 担当コーチ]対象 + `(meeting_id, window)` 冪等性検査 + `withoutOverlapping` 二重防御、Queue 化は別途 Advance 専用チケットで扱う、依存 `S-B-04`)
- ✅ `B-B-01 / content-management-01` コーチが担当資格の教材管理にアクセスすると 403(2026-05-24 詳細化、Step 4 仕込み = 6 Policy [Part / Chapter / Section / SectionQuestion / SectionImage / QuestionCategory] の coach 判定を `false` 固定に置換、Basic 範囲で Policy 修正のみで完結 → 「※」例外注記なし、依存チケットなし)
- ✅ `B-B-02 / content-management-02` 教材管理の Part 一覧が並び順を無視(2026-05-24 詳細化、Step 4 = `Part\IndexAction` の `->ordered()` 削除、※ Action 内 = Basic 範囲外注記)
- ✅ `B-B-03 / content-management-03` 公開停止資格の教材が受講生に露出(2026-05-24 詳細化、⚠️→対応完了: 模範解答 PJ の `Learning\Show{Part,Chapter,Section}Action` に「公開中資格のみ」ガードを追加実装 + テスト追加 [BrowseControllerTest 全通過]、Step 4 でこのガード削除、※ Action 内)
- ✅ `B-B-04 / user-management-02` admin 受講生一覧に退会済みが表示(2026-05-24 詳細化、Step 4 = `User\IndexAction` で `withTrashed()` を無条件化、※ Action 内)
- ✅ `B-B-05 / user-management-03` 在籍ユーザーと同 email の重複招待(2026-05-24 詳細化、Step 4 = `Auth\IssueInvitationAction` の重複ガード削除、入力検証側に unique なし、※ Action 内)
- ✅ `B-B-06 / auth-01` 招待トークン使い回し(2026-05-24 詳細化、Step 4 = `Auth\OnboardAction` の招待使用済み化を削除、※ Action 内)
- ✅ `B-B-07 / certification-management-02` 資格分類削除後のフラッシュ漏れ(2026-05-24 詳細化、Step 4 = `CertificationCategoryController::destroy` の成功フラッシュのみ削除、Basic 範囲 = Controller)
- ✅ `B-B-08 / settings-profile-02` プロフィール更新後のリダイレクト誤り(2026-05-24 詳細化、Step 4 = `ProfileController::update` のリダイレクト先を dashboard に誤らせる、Basic 範囲 = Controller)
- ✅ `B-B-09 / content-management-04` 未登録資格の教材直リンク閲覧(IDOR)(2026-05-24 詳細化、Step 4 = `BrowseController` の show 系から `$this->authorize` 呼び出しを削除、Basic 範囲 = Controller)
- ✅ `B-B-10 / meeting-quota-04` 面談キャンセルで残数返却漏れ(2026-05-24 詳細化、Step 4 = `Meeting\CancelAction` の返却記録作成呼び出しを削除、※ Action 内)
- ✅ `B-B-11 / plan-management-02` プラン一覧の状態フィルタ条件誤り(2026-05-24 詳細化、Step 4 = `Plan\IndexAction` の絞り込みを `PlanStatus::Draft` 固定にコピペミス、※ Action 内、依存 `S-B-03`)
- ✅ `B-B-12 / auth-02` オンボーディング状態遷移漏れで再ログイン不可(2026-05-24 詳細化、Step 4 = `Auth\OnboardAction` の在籍中遷移 1 行削除、※ Action 内)
- ✅ `B-B-13 / auth-03` オンボーディングの確認用パスワード一致漏れ(2026-05-24 詳細化、Step 4 = `OnboardingRequest` の `confirmed` ルール削除、Basic 範囲 = FormRequest)
- ✅ `B-B-14 / chat-01` 未読バッジに自分の発言が混入(2026-05-24 詳細化、Step 4 = `ChatUnreadCountService` の各メソッドから送信者除外条件を削除、※ Service 内)
- ✅ `B-B-15 / auth-04` コーチが管理者専用画面にアクセス可(ロールガード漏れ)(2026-05-24 詳細化 + **題材差し替え**: 旧「withdrawn ログイン可能」は退会=SoftDelete で観測不能のため、観測可能な role ガード漏れに変更、Basic 範囲 = route/Middleware)
- ✅ `B-B-16 / auth-05` 修了ユーザーがプラン機能アクセス可(2026-05-24 詳細化、Step 4 = `EnsureActiveLearning` の利用状態判定行を削除、Basic 範囲 = Middleware)
- ✅ `B-A-01 / mentoring-02` 面談予約の並行リクエストで面談回数二重消費(2026-05-25 詳細化、Step 4 = `MeetingQuota\ConsumeQuotaAction` の `lockForUpdate()` 行 1 行削除、TOCTOU を露出、Advance 範囲 = Action のため「※」例外注記なし、サブカテゴリ「並行性」唯一の Bug、依存チケットなし)
- ✅ `B-A-02 / mock-exam-04` 模試採点で得点率が 0〜1 スケールになり常に不合格(2026-05-25 詳細化、Step 4 = `MockExamSession\GradeAction` の得点率算出から `* 100` 削除、Advance 範囲 = Action のため「※」例外注記なし、波及範囲 `WeaknessAnalysisService` の弱点ヒートマップ / 合格可能性スコアまで Q&A で明示、依存チケットなし)
- ✅ `B-A-03 / enrollment-04` 模試キャンセル後にタームが基礎タームに戻らない(2026-05-25 詳細化、Step 4 = `TermJudgementService` の `whereIn('status', [...])` に `'canceled'` を追加して判定対象を歪める、Advance 範囲 = Service のため「※」例外注記なし、依存チケットなし)
- ✅ `T-B-01 / user-management-01` 管理者のユーザー管理画面の N+1 解消(2026-05-25 詳細化、Step 4 = `User\IndexAction` の `->with('plan')` を取り除く / Before 最大 22 本 → After 3〜4 本 / 単純 Eager Loading パターン、※ Action 内)
- ✅ `T-B-02 / mentoring-05` コーチダッシュボードの担当受講生一覧 N+1 解消(2026-05-25 詳細化、Step 4 = `Dashboard\FetchCoachDashboardAction` の `->with(['user', 'certification'])` + `->withMax('learningSessions as last_activity_at', 'started_at')` を取り除く / Before 1+3N 本 → After 3 本 / 集約取得 + 複合 Eager Loading パターン、※ Action 内)
- ✅ `T-B-03 / horizontal-01` `chunk()` / `chunkById()` / `cursor()` でメモリ最適化(2026-05-25 詳細化、Step 4 = 3 Schedule Command [`GraduateExpiredUsersCommand` / `FailExpiredEnrollmentsCommand` / `Mentoring\AutoCompleteMeetingsCommand`] の `->chunkById(100, ...)` を `->get(); foreach` に巻き戻し、各処理が WHERE 列を更新するため `chunkById()` 必須教材として配置、🕐 `cursor()` 用例は模範解答に存在せず概念教育で補完)
- ✅ `T-A-01 / mentoring-03` 面談予約画面の N+1 解消(2026-05-25 詳細化、Step 4 = `MeetingAvailabilityService::slotsForCertification` の `with('googleCredential')` + `whereIn` を per-coach for-loop に巻き戻し → コーチ N 名で 1+3N クエリ + 連携未設定コーチへの `freebusy` 空打ち発生、`MeetingAvailabilityServiceTest` 既存ケースが振る舞い不変を担保、依存 `S-A-01`)
- ✅ `T-A-02 / mentoring-07` mentoring の Controller method を Action 分離(2026-05-25 詳細化、Step 4 = `MeetingController::store/cancel/upsertMemo` 3 method に Action 内ロジックを展開 + `Meeting\{Store,Cancel,UpsertMemo}Action.php` 3 ファイル削除、取得系 4 method [`index/show/fetchAvailability/indexAsCoach`] は対象外、`DB::transaction` + 具象例外 throw の責務分離を学ばせる、依存 `S-A-01`)
- ✅ `T-A-03 / mentoring-08` Google Calendar 外部連携を Service 分離(2026-05-25 詳細化、Step 4 = `app/Services/Google/{GoogleCalendarService,GoogleOAuthService}.php` を削除し、`MeetingAvailabilityService` / `Meeting\StoreAction` / `Meeting\CancelAction` / `CoachGoogleCredentialController` 内に Google API ライブラリ直接呼出を展開 + トークン期限切れリフレッシュロジック 3 箇所重複、Service 2 分割の責務 [OAuth フロー = stateless / Calendar 操作 = 状態あり] を学ばせる、依存 `S-A-01` / `T-A-02`)
- ✅ `T-A-04 / horizontal-04` モックを用いたテスト追加(外部 API 連携)(2026-05-25 詳細化、Step 4 = `tests/Unit/Services/Google/{GoogleOAuthService,GoogleCalendarService}Test.php` / `tests/Unit/Repositories/GeminiLlmRepositoryTest.php` / `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php` を削除 or 正常系のみに削減 + `Http::preventStrayRequests()` / `#[Group('external')]` / HMAC 署名ヘルパー も剥がす、3 系統のモック手法 [Service = Mockery / Repository = `Http::fake` / Webhook = HMAC ヘルパー] の使い分けを学ばせる、依存 `S-A-01` / `S-A-02` / `S-A-03` / `T-A-03`)
- ✅ `S-A-01 / mentoring-01` Google Calendar 連携(面談予約)(2026-05-25 詳細化、Advance 範囲 = Action / Service、新規 `coach_google_credentials` テーブル + `google_event_id` カラム追加 + `GoogleOAuthService` / `GoogleCalendarService` 2 分割 + `CoachGoogleCredential\{FetchAuthUrl,Store,Destroy}Action` + `Meeting\{Store,Cancel}Action` への組込み + `MeetingAvailabilityService` freebusy 統合 + トークン自動 refresh + API 失敗フォールバック、Step 4 = 全 GCal 関連コードを引き算で外す、依存チケットなし)
- ✅ `S-A-02 / ai-chat-01` Gemini AI チャットボット(2026-05-25 詳細化、Advance 範囲 = Repository / Action / Service、新規 `ai_chat_conversations` / `ai_chat_messages` テーブル + `LlmRepositoryInterface` + `GeminiLlmRepository` + `AiChatPromptBuilderService`(Section パンくず + default_enrollment 解決) + フローティングウィジェット + 同期メッセージ送信 + Transaction A 先行 commit + タイトル LLM 自動生成 + `throttle:ai-chat` 日次 50 通 + 機能 OFF スイッチ、Step 4 = 全 AI 関連コードを引き算で外す、依存チケットなし)
- ✅ `S-A-03 / meeting-quota-02` Stripe 連携(追加面談購入)(2026-05-25 詳細化、Advance 範囲 = Action / Middleware、新規 `payments` テーブル + `PaymentStatus` Enum + `CreateCheckoutSessionAction` + `StripeWebhook\HandleAction`(冪等性ガード + 3 イベント分岐)+ `PurchaseQuotaAction` + `MeetingQuotaCheckoutController` + `VerifyStripeSignature` Middleware + `MeetingQuotaPolicy::purchase` + Stripe SDK 採用、Step 4 = 全 Stripe 関連コードを引き算で外す、`meeting_quota_transactions` 基盤は提供 PJ 既存(消費 / 返却 / 管理者付与は提供 PJ 範囲)、依存 `S-B-02`)
- ✅ `S-A-04 / certification-management-01` 修了証 PDF 出力(2026-05-25 詳細化、Advance 範囲 = Service / Action、`certificates` テーブルは提供 PJ 既存、本チケットで PDF 生成部分を追加 = `CertificatePdfService`(mpdf、A4 横向き、日本語 CJK)+ `CertificateSerialNumberService`(`CT-{YYYYMM}-{NNNNN}` 月内連番 `lockForUpdate`)+ `Certificate\{Issue,Download}Action` + `CertificatePolicy::download`(admin 全件 / coach 担当資格 / student 本人)+ `resources/views/certificates/pdf.blade.php` + `active-learning` Middleware 非適用、Step 4 = PDF 生成 + DL エンドポイントを引き算で外す、依存チケットなし)
- ✅ `S-A-05 / notification-03` Sanctum Cookie 認証追加 + JS フロント通知表示(2026-05-25 詳細化 → 2026-05-26 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成**、AC 11 件、Advance 範囲 = Sanctum + 素の JS、Sanctum stateful 設定 + `auth:sanctum` を通知 API 全 3 ルートに適用 + CSRF Cookie endpoint 公開 + 認証ユーザー本人の通知に切替 + 通知ポップオーバー Blade + JS ポップオーバー制御(タブ切替 / 行クリック既読化 + 遷移 / 全件既読)+ TopBar バッジ動的更新、管理者は対象外、Step 4 = `auth:sanctum` 取り外し + ポップオーバー JS + Blade 削除、依存 `S-B-05`)

## 関連ドキュメント

- `../要件ブレインストーミング.md` § 2 — チケット 40 件草案 fix
- `../../CLAUDE.md` — プロジェクト前提、修了条件 3 条件
- `../../docs/steering/{product,tech,structure}.md` — Feature 完成形定義(構築側のみ)
- `../../docs/specs/{Feature名}/{requirements,design,tasks}.md` — Feature 完成形 SDD(構築側のみ)
- `../../.claude/rules/` — Laravel 実装ルール集
- `../../.claude/rules/ticket-spec.md` — 詳細化規約(本ディレクトリ作業時 auto-load)
- `../../.claude/skills/ticket-detail-100p/` — チケット作成 Skill(`/ticket-detail-100p {ID}`)
- `../評価シート.md` — 本シートから派生(Phase E で作成)
- `../要件シート_詳細度30%/` — 本シートから派生(Phase F で作成)
