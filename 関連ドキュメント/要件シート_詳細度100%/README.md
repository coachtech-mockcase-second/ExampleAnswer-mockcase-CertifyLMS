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
├── Story/                 # 14 件(Basic 9 / Advance 5)
├── Bug/                   # 19 件(Basic 16 / Advance 3)
└── Task/                  # 8 件(Basic 2 / Advance 6)
```

**合計 41 件 / 222h（目標 225h ± 10% = 202.5〜247.5h 内）**。Basic / Advance はファイル名(`S-B-XX` / `S-A-XX` 等)で識別する(サブディレクトリで階層化しない)。

> テンプレ(`story.md` / `bug.md` / `task.md`)は `.claude/skills/ticket-detail-100p/templates/` 配下。本ディレクトリには配置しない。

## ファイル命名規則

`{チケットID}_{Feature略称-連番}.md`(例: `S-B-01_qa-board-01.md` / `B-B-09_content-management-04.md`)。`B` = Basic / `A` = Advance。

## 採点者の使い方

**採点者** が本シートで各チケットを評価する標準フロー:

1. 該当チケットの **PR を確認**(file Changes で実装内容、画面動作で振る舞い)
2. 本シート対象チケット(`Story/{ID}.md` 等)の **受け入れ条件** をチェックリスト形式で 1 項目ずつ判定
3. 受け入れ条件 = 評価シート「チケット要件」の 1 採点行 と 1:1 対応(採点シートに転記)
4. 横断採点(「横断品質」= コード品質 / テスト品質 / README / PR 記述)は評価シート側で行う(チケット単位の作業ではない)

> 受講生からのヒアリング応答は **コーチ** の役割(本シートの **実装方針** + **補足** セクションを参照)。採点者は通常これらのセクションを見ない。

## 評価シートとの対応関係

本シートは評価シートの **直接の入力源**。対応関係:

| 評価シート大項目 | 中身 | 本シートとの関係 |
|---|---|---|
| チケット要件(73.9%) | 各チケットの受け入れ条件を採点行に展開 | 各 .md の **受け入れ条件** が 1 項目 = 1 採点行 |
| 横断品質(26.1%) | コード品質(Pint / 命名 / N+1 / 既存パターン踏襲 / 型宣言 / 外部 API の .env 管理) + テスト品質(全 pass / カバレッジ) + README(外部 API の .env 等の追記) + PR 7 セクション記述の全体達成度 | 個別チケットではなく受講生が追加・変更した差分を全 PR 横断で判定。配点抑えめの品質安全網 |

「テスト品質(全 pass / カバレッジ)」「PR 7 セクション記述」は **チケット個別の受け入れ条件には書かない**(横断採点として「横断品質」に集約)。**PR は全 41 件を逐一二値判定せず**、チケット要件の採点で各 PR を開く際に 7 セクション記述を併せて観察し、横断品質で全体達成度を段階評価する。動画は各 PR の「動作確認」の一部、ONBOARDING 改修は対象外。テスト実装の有無のみチケット要件の各 AC 行に含む(3.5/3.6)。

## 詳細化規約

詳細化中の判断基準(受け入れ条件原則 / 振る舞いと文言の分離 / Basic 制約 / ロール表記 / 採点必要レイヤー区分 / Bug 修正範囲 / 依存記録方向 / 模範解答 PJ 懐疑的レビュー)は **`../../.claude/rules/ticket-spec.md`** に集約(paths で本ディレクトリ作業時 auto-load)。

## チケット詳細化 Skill

`/ticket-detail-100p {チケットID}` で詳細化(Skill: `../../.claude/skills/ticket-detail-100p/`)。複数チケットの並列生成にも対応(単一セッション連続 or worktree-spawn 連携)。詳細は `SKILL.md` 参照。

## 進行順

**全 Basic → 全 Advance / Story → Bug → Task**:

```
Story Basic (9) → Bug Basic (16) → Task Basic (2) → ★ Basic 完成
→ Story Advance (5) → Bug Advance (3) → Task Advance (6) → ★ 全件完成
```

- **Basic → Advance**: Advance は Basic 上に積む拡張(例: S-A-05 の Sanctum 認証 + JS 通知フロントは S-B-04 通知基盤の上に後付け)。Basic 確定後に Advance を詳細化するほうが整合が取りやすい
- **Story → Bug → Task**: 記述スタイルが異なる(特に Bug は実装方針なし)ため、種別単位で進める

## チケット一覧(41 件)

> 出所: `../要件ブレインストーミング.md` § 2(2026-05-21 草案 fix)。Skill 実行時の入力情報源として使用。

### Story Basic(9 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `S-B-01` | `qa-board-01` | 質問掲示板 | 新規機能の構築 | 12h |
| `S-B-02` | `meeting-quota-01` | 面談パックのマスタ管理 | 既存機能の拡張 | 6h |
| `S-B-03` | `plan-management-01` | プランのマスタ管理 | 既存機能の拡張 | 6h |
| `S-B-04` | `notification-01` | 通知基盤（アプリ内＋メール） | 新規機能の構築 | 11h |
| `S-B-06` | `enrollment-03` | 個人学習目標の管理 | 新規機能の構築 | 6h |
| `S-B-07` | `settings-profile-01` | 設定・プロフィール | 既存機能の拡張 | 6h |
| `S-B-08` | `mentoring-06` | 受講生メモの管理（コーチ） | 新規機能の構築 | 6h |
| `S-B-09` | `notification-05` | お知らせ配信（管理者） | 新規機能の構築 | 7h |
| `S-B-10` | `notification-04` | 面談リマインダー通知（前日・1時間前） | 新規機能の構築 | 5h |

> **Story 改訂(2026-06-09)**: `S-B-05`(通知 JSON API〔認証なし〕)は `S-A-05`(Sanctum Cookie 認証 + JS フロント通知表示)へ統合し廃止。認証付き通知 API の構築は `S-A-05` が単独で担う(`api.php` ルートを既存 web セッションで認証するには Sanctum〔Advance 範囲〕が必須で、認証付き API は Basic 単独では成立しないため)。`S-B-05` の ID は欠番として扱う(`T-B-01` 前例に倣う)。Story Basic 10→9 件・全 42→41 件。

### Story Advance(5 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `S-A-01` | `mentoring-01` | Google Calendar 連携（面談予約） | 既存機能の拡張 | 14h |
| `S-A-02` | `ai-chat-01` | Gemini AI チャットボット | 新規機能の構築 | 11h |
| `S-A-03` | `meeting-quota-02` | Stripe 連携（追加面談購入） | 新規機能の構築 | 13h |
| `S-A-04` | `certification-management-01` | 修了証 PDF 出力 | 既存機能の拡張 | 5h |
| `S-A-05` | `notification-03` | Sanctum Cookie 認証 + JS フロント通知表示 | 既存機能の拡張 | 12h |

### Bug Basic(16 件)

> **Step 4 仕込み方の前提**: 模範解答 PJ は `backend-http.md` の規約(Controller は薄く / メソッド内ビジネスロジック原則 0 行 / 1 Controller method = 1 Action)に従って、ビジネスロジックを Action / Service / FormRequest / Policy / Middleware に分散している。「Step 4 仕込み方」列は **模範解答 PJ の実装レイヤー基準** で記述し、Step 4 引き算実装時の指示書として正確な仕込み箇所を示す。

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 | Step 4 仕込み方 |
|---|---|---|---|---|---|
| `B-B-01` | `content-management-01` | コーチが担当資格の教材管理にアクセスすると 403 | 認可・認証 | 4h | コーチ Policy で `denied` を返す |
| `B-B-02` | `content-management-02` | 教材管理の階層一覧（Part / Chapter / Section）が並び順を無視して表示される | 機能(ソート) | 3.5h | parts/chapters/sections migration の `(親id, order)` 複合 index を除去 + `Part\IndexAction`(Part 一覧、トップレベル `->ordered()`) / `Part\ShowAction`(Chapter、Eager Load クロージャ) / `Chapter\ShowAction`(Section、Eager Load クロージャ) の 3 箇所で整列(`->ordered()`)を削除。index と明示整列の両方が無いため登録順で表示され画面・テストで観測可能(同梱 `*_in_order_ascending` 3 件が赤) |
| `B-B-03` | `content-management-03` | 公開停止した資格の教材が受講生に閲覧できてしまう | データ(絞り込み漏れ) | 3h | 模範解答 PJ の `Learning\Show{Part,Chapter,Section}Action` に「親資格が公開中(`CertificationStatus::Published`)でなければ 404」ガードを追加実装済(2026-05-24、`BrowseControllerTest` に archived 404 ケース 3 件追加 → 全通過) → Step 4 でこのガード 3 箇所を削除し、archive された資格の教材が受講登録済み受講生に閲覧可能になる。※ Action 内のため Basic 範囲外注記あり |
| `B-B-04` | `user-management-02` | 管理者の受講生一覧に退会済みユーザーが表示される | データ(クエリ漏れ) | 3h | `User\IndexAction` の `if ($status === UserStatus::Withdrawn) { $query->withTrashed(); }` 条件を取り払って `$query->withTrashed();` を冒頭で常時呼ぶ(通常一覧にも SoftDelete 済 withdrawn ユーザーが混入) |
| `B-B-05` | `mock-exam-05` | 模試の合格点を 100% 超に設定でき誰も合格できなくなる | データ(バリデーション) | 2.5h | `MockExam\StoreRequest` / `MockExam\UpdateRequest` の `passing_score` 許容範囲の上限検証を外す(`between:1,100`→`min:1`)。合格点 100% 超(例 150%)の模試が作成でき、得点率は最大 100% なので満点でも合格基準に届かず全員不合格になる。下限(1 以上)側は正しいまま |
| `B-B-06` | `auth-01` | 招待トークンが使い回せてしまう | セキュリティ(トークン期限管理) | 3h | `Auth\OnboardAction` の `$invitation->forceFill(['status' => InvitationStatus::Accepted, 'accepted_at' => $now])->save();` を削除(使用済化忘れ → status が Pending のまま → 同じトークンで何度でも再オンボード可能) |
| `B-B-07` | `certification-management-02` | 資格分類マスタの削除後に成功メッセージが表示されない | UI/UX(フラッシュ) | 2h | `CertificationCategoryController::destroy()` の `redirect()->route('admin.certification-categories.index')->with('success', '分類を削除しました。')` から `with('success', ...)` のみ削除(store / update は正常維持で destroy のみ漏れのコピペミス) |
| `B-B-08` | `enrollment-05` | 目標受験日の更新後にダッシュボードへ遷移してしまう | UI/UX(リダイレクト) | 2h | `EnrollmentController::updateExamDate` の成功時リダイレクト先を、その受講登録の詳細(`enrollments.show`)からダッシュボード(`dashboard.index`)に差し替える(成功フラッシュは残す → 更新は成功するが画面が飛ぶ) |
| `B-B-09` | `content-management-04` | 受講登録していない資格の教材詳細を直リンクで閲覧できてしまう | 認可・認証(IDOR) | 3h | 受講生向け教材閲覧 `Learning\BrowseController` の `showSection()` / `showPart()` / `showChapter()` から `$this->authorize('learning.section.view', ...)` 等の呼び出しを削除(`showEnrollment()` は Enrollment 所有者判定の認可で教材 IDOR とは別概念のため対象外。対象は learning Feature であり content-management の admin 系教材管理ではない) |
| `B-B-10` | `meeting-quota-04` | 面談をキャンセルしても面談回数が返却されない | データ(Tx 漏れ) | 3h | 面談キャンセル処理から面談回数の返却記録(`MeetingQuotaTransaction.refunded`)作成呼び出しを削除。本クラスタ(03)では T-A-02 が `Meeting\CancelAction` を `MeetingController::cancel` にインライン化するため注入先は Controller(返却呼び出し 1 行削除、`$refundAction` 注入は手がかりとして温存)。citation = `tests/Feature/Http/Meeting/MeetingControllerTest.php::test_cancel_refunds_meeting_quota`(元 `CancelActionTest` は T-A-02 で削除) |
| `B-B-11` | `certification-management-03` | 資格マスタ一覧の状態フィルタが正しく絞り込めない | データ(クエリ条件誤り) | 3h | `Certification\IndexAction`(資格一覧取得の status フィルタ)で、画面指定の状態値ではなく `where('status', CertificationStatus::Draft->value)` 固定で絞り込んでしまう enum 値コピペミス(公開中を指定しても下書きで絞られ非表示)。citation = 既存 `tests/Feature/Http/Certification/IndexTest.php::test_status_filter_returns_only_matching_status`。Story を持たない既存 Feature が対象で依存なし |
| `B-B-12` | `auth-02` | オンボーディング完了後に再ログインできなくなる | 機能(状態遷移) | 3h | `Auth\OnboardAction` の `$user->update(['status' => UserStatus::InProgress])` 行を削除 |
| `B-B-13` | `auth-03` | オンボーディングで確認用パスワードが一致しなくても登録できる | データ(バリデーション) | 2h | FormRequest の `'password' => 'confirmed'` ルールを抜く |
| `B-B-14` | `chat-01` | チャットの未読バッジに自分の発言までカウントされる | データ(クエリ条件誤り) | 3h | `ChatUnreadCountService` の3メソッド(`messageCountInRoom` / `messageCountsByRoomForUser` / `roomCountForUser`)から送信者除外条件 `where('sender_user_id', '!=', $user->id)` を削除(同条件が3箇所に分散) |
| `B-B-15` | `enrollment-06` | コーチの受講生一覧に担当外の受講生まで表示される | 認可・認証(担当スコープ漏れ) | 3h | `Enrollment::scopeForUser` のコーチ分岐で、担当割り当て資格への絞り込み(`whereHas('certification.coaches', ...)`)を外し全件取得(管理者と同じ `$query`)にする → コーチが受講登録管理一覧で担当外の受講生まで閲覧可。管理者(全件) / 受講生(自分のみ)分岐は正しいまま |
| `B-B-16` | `auth-05` | 修了したユーザーがプラン機能にアクセスできてしまう | 認可・認証(Middleware チェック漏れ) | 3h | 既存 `EnsureActiveLearning` Middleware から status 判定行を削除 |

### Bug Advance(3 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 | Step 4 仕込み方 |
|---|---|---|---|---|---|
| `B-A-01` | `mentoring-02` | 面談予約で同一コーチ・同一時刻枠が二重予約される | 並行性 | 5h | `meetings` テーブルの `$table->unique(['coach_id', 'scheduled_at']);` 行(migration)を削除(層2 = DB 一意制約のみ除去)。`Meeting\StoreAction` の予約済コーチ除外(層1、`findAvailableCoaches` の `whereDoesntHave`)と INSERT の `UniqueConstraintViolationException`→409 catch は **温存**(catch は受講生への手がかり)。逐次は層1が防ぐため二重予約は並行リクエストでのみ顕在。決定論採点は同コーチ×同時刻の `canceled` Meeting を事前 INSERT して層1をすり抜けさせ制約発火を確認。citation は HTTP 層 `tests/Feature/Http/Meeting/MeetingControllerTest.php::test_store_blocks_double_booking_for_same_coach_and_slot`(元 `StoreActionTest` は T-A-02 で削除されるため移設)。catch は T-A-02 で `MeetingController::store` にインライン後も温存 |
| `B-A-02` | `mock-exam-04` | 模試の採点結果が常に不合格になる | 機能(計算) | 6h | `MockExamSession\GradeAction` の `$scorePercentage = round($totalCorrect / $totalQuestions * 100, 2)` から `* 100` を削除(75% のはずが 0.75% として保存され、合否判定 `>= passing_score_snapshot` で全員不合格扱い)。クラス名は `ScoringAction` / `ScoringService` ではなく `MockExamSession\GradeAction` |
| `B-A-03` | `enrollment-04` | 模試をキャンセルしても学習タームが基礎タームに戻らない | 機能(計算 + クエリ) | 4h | `TermJudgementService` の `status IN (...)` に `canceled` を含めるミス |

### Task Basic(2 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `T-B-02` | `mentoring-05` | コーチダッシュボードの担当受講生一覧の N+1 解消 | パフォーマンス | 4h |
| `T-B-03` | `horizontal-01` | 大量レコードを扱う定期実行処理のメモリ最適化（chunk 処理） | パフォーマンス | 5h |

### Task Advance(6 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `T-A-01` | `mock-exam-06` | 模試マスタ一覧の N+1 解消 | パフォーマンス | 5.5h |
| `T-A-02` | `mentoring-07` | 面談機能のロジックを Action へ分離 | リファクタリング | 5h |
| `T-A-03` | `learning-01` | 学習進捗の集計ロジックを Service へ集約 | リファクタリング | 4h |
| `T-A-04` | `horizontal-04` | 外部 API 連携のモックテスト追加 | リファクタリング | 5.5h |
| `T-A-05` | `horizontal-05` | 通知・メール配信の非同期化（キュー + worker） | パフォーマンス | 6.5h |
| `T-A-06` | `dashboard-01` | 管理者ダッシュボード集計のキャッシュ化 | パフォーマンス | 5.5h |

> **Task 改訂(2026-05-31)**: `T-B-01`(admin ユーザー一覧 N+1)は `T-B-02` / `T-A-01` と学習が重複するため廃止し、Advance パフォーマンス課題 `T-A-06`(管理者ダッシュボード集計のキャッシュ化)を追加。工数は相殺(−3.5h + 5.5h)、Task 計 8 件は不変(当時の全 42 件 → 後日 `S-B-05` 統合で 41 件)。`T-B-01` の ID は欠番として扱う(歴史ログ `_review-log.md` / `_basic-constraint-audit.md` は当時のスナップショットとして保持)。

## Bug 推奨着手順(難易度ランプ)

Bug の着手順は自由だが、コードリーディング → 原因特定 → 修正の認知負荷が軽い順に並べた推奨ランプ(ID は変更しない / 同 Feature の依存 Story を終えている前提):

1. **ウォームアップ(Controller 1 行)**: `B-B-07`(フラッシュ漏れ)→ `B-B-08`(リダイレクト先)
2. **単純クエリ / 検証**: `B-B-04`(退会済み一覧混入)→ `B-B-05`(模試の合格点検証)→ `B-B-11`(資格マスタの状態フィルタ ※依存なし)
3. **認可(Policy / ガード)**: `B-B-01`(コーチ教材 403)→ `B-B-03`(archived 露出)→ `B-B-09`(IDOR)
4. **認証ライフサイクル**: `B-B-06`(招待トークン使い回し)→ `B-B-12`(status 遷移)→ `B-B-13`(確認用パスワード)
5. **クエリ / 集計**: `B-B-02`(教材階層ソート)→ `B-B-10`(面談残数返却)→ `B-B-14`(未読バッジ)
6. **認可(ガード・スコープ漏れ)**: `B-B-15`(コーチの受講生一覧スコープ)→ `B-B-16`(修了者のプラン機能)
7. **Advance(並行性 / 計算 / 集計)**: `B-A-02`(得点率計算)→ `B-A-03`(ターム判定)→ `B-A-01`(面談二重予約)

> auth 系の `B-B-06` → `B-B-12` はオンボーディング完了フローの整合確認の都合でこの順が自然(強制依存ではない / 同一ファイル `OnboardAction` を触るが修正箇所が別で衝突なし)。

## 進捗トラッカー

| 種別 | Basic | Advance | 計 |
|---|---:|---:|---:|
| Story | 9 / 9 | 5 / 5 | 14 / 14 |
| Bug | 16 / 16 | 3 / 3 | 19 / 19 |
| Task | 2 / 2 | 6 / 6 | 8 / 8 |
| **計** | **27 / 27** | **14 / 14** | **41 / 41** |

### 完成済み

- ✅ `S-B-01 / qa-board-01` 質問掲示板の実装(2026-05-23 サイドバーバッジ機能廃止 / 通知発火スコープ外 [`S-B-04` に移管] で書き直し → 2026-05-27 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成** → **2026-05-28 模範解答 PJ の delete を Policy(人)/ Action(状態)責務分離に修正**(回答あり削除を Policy 403 先取りから Action 409 へ統一、死にコード解消、qa-board 83 テスト pass) → **2026-05-28 解決マーク / 解除を冪等トグルに変更**(重複操作の 409 を廃止し冪等 no-op 化、Resolve/Unresolve 2 例外を削除、qa-board 83 テスト pass)、AC 10 件、Basic 範囲で Controller 内完結を前提に記述、実装方針を「インターフェース(認可列含む)→ データモデル(制約列含む)→ コンポーネント(クラス名 + ファイルパス集約)→ 異常系 → 設計判断(テスト観点内包)」の外部→内部潜行順 5 構造に再編、依存チケットなし → **2026-05-28 qa-board の active-learning 非適用 → 適用に訂正**: 修了 / 退会受講生は 403、教材閲覧・チャット等の他プラン機能と統一、`B-B-16` と整合(実装 routes/web.php + `EnsureActiveLearning` docblock が元から適用、ユーザー確認済))
- ✅ `S-B-02 / meeting-quota-01` 面談パックマスタ管理(admin マスタ CRUD)(2026-05-23 詳細化 → **2026-05-28 実装方針 5 サブセクション構造で再生成**、AC 13→8 件、やること/やらないこと→要件/スコープ外 改名 + 概要を背景・目的へ統合、実装方針を「インターフェース(認可列)→ データモデル(既存 meeting_packs + 制約列)→ コンポーネント(クラス + パス集約)→ 異常系 → 設計判断」に再編、Basic 範囲で Controller 内完結を前提・Action は ※ 注記、状態遷移 3 種 + 公開中削除ガード(409)、**削除方式を SoftDelete → 物理削除に訂正**(模範解答 PJ `MeetingPack` は SoftDeletes trait / deleted_at なし = `backend-models.md` マスタ系 SoftDelete 不採用規約 + 姉妹 `Plan` と一致、ユーザー確認済 2026-05-28)、依存チケットなし)
- ✅ `S-B-03 / plan-management-01` プラン管理 Admin マスタ UI(2026-05-25 詳細化 → **2026-05-28 実装方針 5 サブセクション構造で再生成**、AC 9 件、Basic 範囲で Controller 内完結を前提に記述、やること/やらないこと→要件/スコープ外 改名 + 概要を背景・目的へ統合、実装方針を「インターフェース(認可列)→ データモデル(既存 plans テーブル + 制約列)→ コンポーネント(クラス + パス集約)→ 異常系 → 設計判断(テスト観点内包)」の外部→内部潜行順に再編、CRUD + 状態遷移 3 種 + 削除 2 段ガード(物理削除 = 下書き × 受講者なしのみ)、🕐 例外クラスのメッセージ責務は Phase D で MeetingPack と統一、Step 4 引き算は B-B-11 と重複あり Phase D 課題 #3 で整合確認、依存チケットなし)
- ✅ `S-B-04 / notification-01` 通知基盤(Laravel Notification、DB + Mail)(2026-05-25 詳細化 → 2026-05-26 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成**、AC 10 件、4 通知種別[chat / Q&A / 面談予約 / 面談キャンセル]+ サイドバー通知一覧 / 行クリック既読化 + 全件既読化、管理者は受信対象から除外、リマインダー / Announcement / API / Pusher / Sanctum は別チケット、依存チケットなし)
- ⛔ `S-B-05 / notification-02` 通知 JSON API(認証なし) — **2026-06-09 廃止し `S-A-05` へ統合**(認証付き通知 API の構築〔`api.php` ルート 3 本 + Resource + API FormRequest〕を `S-A-05` 単独が担う。`api.php` ルートを既存 web セッションで認証するには Sanctum〔Advance 範囲〕が必須で、認証なし版は段階設計の便宜だったため統合。ID は欠番として扱う)
- ✅ `S-B-06 / enrollment-03` 個人目標(EnrollmentGoal)CRUD(2026-05-25 詳細化、Basic 範囲で Controller 内完結を前提に記述、新規テーブル `enrollment_goals` + Policy 責務委譲(`view` を `EnrollmentPolicy::view` に委譲)+ 達成マーク / 達成解除のべき等性 + 編集は専用ページ + 削除は HTML confirm、コーチ / 管理者は閲覧専用、依存チケットなし → **2026-05-28 実装方針 5 サブセクション構造で再生成**、AC 19→8 件に圧縮[業務操作の側面分解 + 認可機能群統合]、やること/やらないこと→要件/スコープ外 に改名、独立 Seeder 設計節を廃止しデータモデル節に統合 + Seeder は `EnrollmentSeeder` 内投入に事実修正[独立 `EnrollmentGoalSeeder` は存在しない])
- ✅ `S-B-07 / settings-profile-01` 設定・プロフィール画面(2026-05-25 詳細化 → **2026-05-28 実装方針 5 サブセクション構造で再生成**、AC 23→8 件に圧縮、Basic 範囲で全ロール共通の 2 タブ[プロフィール / パスワード]+ アバター変更 + コーチ専用固定面談 URL 編集、Fortify Password Update 活用、`UserPolicy::updateSelf` 新設、`EnsureActiveLearning` Middleware 不適用で graduated もアクセス可、面談設定タブ / Google Calendar 連携は `S-A-01` で扱う)
- ✅ `S-B-08 / mentoring-06` コーチ用 受講生メモ(EnrollmentNote)編集(2026-05-25 詳細化、Basic 範囲で Controller 内完結を前提に記述、新規テーブル `enrollment_notes` + Policy 二重判定[担当コーチ + 作成者本人 + 管理者越境] + 受講生は閲覧含め完全 403 + 編集時の作成者(`coach_user_id`)不変性、`EnrollmentGoal`(S-B-06)と役割対称ペア、依存チケットなし → **2026-05-28 新 Story 構造(実装方針 5 サブセクション + フラット AC)で再生成**: 概要を背景・目的へ統合 + やること/やらないこと→要件/スコープ外 + 実装方針を 5 サブセクション[インターフェース → データモデル → コンポーネント → 異常系 → 設計判断]に再編 + AC 19→7 件にフラット化 + 主要URL/バリデーション/認可設計/テスト観点/関連ファイルメモ節を統合 + テスト AC 追加)
- ✅ `S-B-09 / notification-05` admin お知らせ配信機能(2026-05-25 詳細化 → 2026-05-26 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成**、AC 11 件、新規お知らせエンティティ + 配信対象タイプ 3 種[全受講生 / 資格指定 / ユーザー指定]+ `S-B-04` 通知基盤利用 + 業務トランザクション確定後の配信発火 + 受講中フィルタ + 配信不可逆(再配信 / 編集 / 取消なし)、依存 `S-B-04`)
- ✅ `S-B-10 / notification-04` 面談リマインダー通知(前日 + 1 時間前)(2026-05-27 新規作成、Basic 範囲で Schedule Command + 同期発火 + Queue なし、`notifications:send-meeting-reminders --window={eve\|one_hour_before}` の 2 タイミング配信 + 当事者全員[受講生 + 担当コーチ]対象 + `(meeting_id, window)` 冪等性検査 + `withoutOverlapping` 二重防御、Queue 化は別途 Advance 専用チケットで扱う、依存 `S-B-04`)
- ✅ `B-B-01 / content-management-01` コーチが担当資格の教材管理にアクセスすると 403(2026-05-24 詳細化、Step 4 仕込み = 6 Policy [Part / Chapter / Section / SectionQuestion / SectionImage / QuestionCategory] の coach 判定を `false` 固定に置換、Basic 範囲で Policy 修正のみで完結 → 「※」例外注記なし、依存チケットなし → **2026-05-28 規約刷新版で再生成**: 実装方針を Bug 新テンプレ[原因のみ]に再編 + 主要 URL 表廃止 + 構築側メタ(Step 4 表現)除去 + テスト AC 追加、AC 3 件)
- ✅ `B-B-02 / content-management-02` 教材管理の階層一覧(Part/Chapter/Section)が並び順を無視(2026-05-24 詳細化、Step 4 = `Part\IndexAction` の `->ordered()` 削除、※ Action 内 = Basic 範囲外注記 → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + テスト AC 追加、AC 2 件 → **2026-05-30 スコープ 3 階層拡張**: Part 単独 → 教材階層 3 層(Part/Chapter/Section)。仕込み 3 Action[`Part\IndexAction` トップレベル整列 / `Part\ShowAction`・`Chapter\ShowAction` の Eager Load 制約クロージャ整列]、工数 2.5h→3.5h、タイトル変更、AC は 3 階層をサブ箇条書き 1 採点項目に統合) → **2026-06-09 観測可能な Bug に再設計**: 旧版は parts/chapters/sections の `(親id, order)` 複合 index が偶然 order 順を保証し画面・同梱テストで観測できず「コード確認 AC」に reframe していた(振る舞いベース採点に反する)。本質は「暗黙のインデックス順序依存をやめ明示整列する」= Bug として観測可能であるべきため、両 PJ の order 複合 index を除去 → `->ordered()` 削除で登録順表示が画面・テストで観測可能に。コード確認 AC を撤回し振る舞い行 + 同梱テスト citation(Part/Chapter/Section の各 `*_in_order_ascending` 3 件、提供 PJ で赤)に。教材メッセージ「インデックス順に暗黙依存するな」とも整合。評価シートは 2 点 1 行→振る舞い 1 点 + テスト 1 点の 2 行(配点合計不変・評価ライン不変、項目数 218→219)
- ✅ `B-B-03 / content-management-03` 公開停止資格の教材が受講生に露出(2026-05-24 詳細化、⚠️→対応完了: 模範解答 PJ の `Learning\Show{Part,Chapter,Section}Action` に「公開中資格のみ」ガードを追加実装 + テスト追加 [BrowseControllerTest 全通過]、Step 4 でこのガード削除、※ Action 内 → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + B-B-09 参照 Q&A 削除 + 主要 URL 表廃止 + テスト AC 追加、AC 3 件)
- ✅ `B-B-04 / user-management-02` admin 受講生一覧に退会済みが表示(2026-05-24 詳細化 → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + 期待/実際を「結果」に改名 + テスト AC 追加 + ※ Action 内 Basic 範囲外注記を原因節にインライン短縮、AC 3 件、Step 4 = `User\IndexAction` で `withTrashed()` を無条件化、依存チケットなし)
- ✅ `B-B-05 / mock-exam-05` 模試の合格点を 100% 超に設定でき誰も合格できなくなる(**2026-06-02 題材差し替え**: 旧「在籍ユーザーと同 email の重複招待」はガード除去で email UNIQUE 違反の 500 になり「静かな重複」が構造的に再現不可だったため、画面再現可能・実務的な「合格点バリデーションの上限欠落」へ変更。Step 4 = `MockExam StoreRequest/UpdateRequest` の `passing_score` を `between:1,100`→`min:1`(上限欠落)、合格点 100% 超 → 満点でも不合格(全員不合格)、AC 3 件、citation `StoreRequestTest::test_validation_fails`(passing_score 101)、依存チケットなし)
- ✅ `B-B-06 / auth-01` 招待トークン使い回し(2026-05-24 詳細化、Step 4 = `Auth\OnboardAction` の招待使用済み化を削除、※ Action 内 → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + 期待/実際を「結果」に改名 + テスト AC 追加 + ※ Action 内 Basic 範囲外注記を原因節にインライン短縮、AC 3 件)
- ✅ `B-B-07 / certification-management-02` 資格分類削除後のフラッシュ漏れ(2026-05-24 詳細化、Step 4 = `CertificationCategoryController::destroy` の成功フラッシュのみ削除、Basic 範囲 = Controller → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + テスト AC 追加 + 期待/実際を「結果」に改名、AC 3 件)
- ✅ `B-B-08 / enrollment-05` 目標受験日の更新後にダッシュボードへ遷移(**2026-06-02 retarget**: 旧仕込み先 `ProfileController::update` が設定プロフィール全 gut で消滅したため、自己サービス更新の忠実な代替 `EnrollmentController::updateExamDate` へ移設。Step 4 = 成功時リダイレクトを `enrollments.show`→`dashboard.index`(成功フラッシュは残す)、AC 3 件、citation `UpdateExamDateTest::test_student_can_set_own_exam_date`、依存チケットなし)
- ✅ `B-B-09 / content-management-04` 未登録資格の教材直リンク閲覧(IDOR)(2026-05-24 詳細化、Step 4 = `BrowseController` の show 系から `$this->authorize` 呼び出しを削除、Basic 範囲 = Controller → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + B-B-03 参照 Q&A 削除 + 主要 URL 表廃止 + テスト AC 追加、AC 3 件)
- ✅ `B-B-10 / meeting-quota-04` 面談キャンセルで残数返却漏れ(2026-05-24 詳細化、Step 4 = `Meeting\CancelAction` の返却記録作成呼び出しを削除、※ Action 内 → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + 期待/実際を「結果」に改名 + テスト AC 追加 + ※ Action 内 Basic 範囲外注記(長文)を概要直下に維持、AC 3 件、残数集計 = `MeetingQuotaService::remaining` / 返却記録生成 = `MeetingQuota\RefundQuotaAction` / 完了フラッシュ「面談をキャンセルしました。面談回数を返却しました。」が表示↔残数不変の食い違いを手がかりに明示、依存なし)
- ✅ `B-B-11 / certification-management-03` 資格マスタ一覧の状態フィルタ条件誤り(**2026-06-09 題材差し替え**: 旧 `plan-management-02`〔プラン一覧の状態フィルタ〕は仕込み先 `Plan\IndexAction` が `S-B-03`〔プラン管理 Admin UI = 拡張 Story〕の新規実装スコープ内で提供 PJ に存在せず、赤テストを先出しできない構造破綻のため、Story を持たない既存 Feature の同型バグへ差し替え〔B-B-05/08/15 の再ターゲット前例に倣う〕。Step 4 = `Certification\IndexAction` の status 絞り込みを `CertificationStatus::Draft` 固定にコピペミス、AC 3 件、citation = 既存 `tests/Feature/Http/Certification/IndexTest.php::test_status_filter_returns_only_matching_status`、依存なし)
- ✅ `B-B-12 / auth-02` オンボーディング状態遷移漏れで再ログイン不可(2026-05-24 詳細化、Step 4 = `Auth\OnboardAction` の在籍中遷移 1 行削除、※ Action 内 → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + 期待/実際を「結果」に改名 + テスト AC 追加 + ※ Action 内 Basic 範囲外注記を原因節にインライン短縮 + ログイン認証(`AuthenticateUserUsing`)/ `EnsureActiveLearning` 参照を原因に明記、AC 3 件)
- ✅ `B-B-13 / auth-03` オンボーディングの確認用パスワード一致漏れ(2026-05-24 詳細化、Step 4 = `OnboardingRequest` の `confirmed` ルール削除、Basic 範囲 = FormRequest → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + 期待/実際を「結果」に改名 + テスト AC 追加、AC 3 件)
- ✅ `B-B-14 / chat-01` 未読バッジに自分の発言が混入(2026-05-24 詳細化 → **2026-05-28 規約刷新版で再生成**、AC 3 件[自分の発言を未読集計から除外 / 相手未読の正常系維持 / テスト実装]、実装方針を Bug 新テンプレ[原因のみ]に再編 + ※ Service 内 Basic 範囲外注記を原因サブセクションにインライン短縮、再現手順を Seeder 状態ベースに精緻化[`StoreMessageAction` / `ShowAction` が送信者の `last_read_at` を二重更新するため "送信→即サイドバー観測" では非再現と判明]、Step 4 = `ChatUnreadCountService` 3 メソッド[`messageCountInRoom` / `messageCountsByRoomForUser` / `roomCountForUser`]から送信者除外条件を削除、※ Service 内、依存なし)
- ✅ `B-B-15 / enrollment-06` コーチの受講生一覧に担当外の受講生まで表示される(**2026-06-02 題材差し替え**: 旧「コーチが管理者専用画面にアクセス可」は admin 全画面が Policy/FormRequest で二重ガードされミドルウェアを緩めても観測不能(赤テスト 0 件)だったため、画面ナビのみで再現可能な「コーチ受講生一覧の担当スコープ漏れ」へ変更。Step 4 = `Enrollment::scopeForUser` のコーチ分岐の担当資格絞り込みを外し全件化、AC 3 件、citation `CoachStudent/IndexTest::test_coach_sees_only_enrollments_in_assigned_certifications`(HTTP・観察的)、依存チケットなし)
- ✅ `B-B-16 / auth-05` 修了ユーザーがプラン機能アクセス可(2026-05-24 詳細化、Step 4 = `EnsureActiveLearning` の利用状態判定行を削除、Basic 範囲 = Middleware → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ] + 主要 URL 表廃止 + 期待/実際を「結果」に改名 + テスト AC 追加、AC 3 件、**qa-board の active-learning ガードを対象に維持**(実装 routes/web.php + `EnsureActiveLearning` docblock に整合 → S-B-01 の「永続閲覧/非適用」記述を「受講中のみ」へ訂正し B-B-16 と整合、ユーザー確認済 2026-05-28))
- ✅ `B-A-01 / mentoring-02` 面談予約で同一コーチ・同一時刻枠が二重予約される(2026-05-25 初版 → 2026-05-28 規約刷新版で再生成 → **2026-05-31 題材を全面差し替え**: 旧「面談回数の二重消費(quota / `lockForUpdate`)」から「同一コーチ・同一時刻枠の二重予約(`meetings` の `(coach_id, scheduled_at)` UNIQUE 欠如)」へ。層2のみ仕込み[層1 = 予約済コーチ除外は温存]、採点は §3.6 〔テスト〕タグ[決定論 = canceled 衝突]、工数 6h→5h、AC 3 件。模範解答はコード変更不要[一意制約 + catch + 決定論テスト 既存])
- ✅ `B-A-02 / mock-exam-04` 模試採点で得点率が 0〜1 スケールになり常に不合格(2026-05-25 詳細化、Step 4 = `MockExamSession\GradeAction` の得点率算出から `* 100` 削除、Advance 範囲 = Action のため「※」例外注記なし、波及範囲 `WeaknessAnalysisService` の弱点ヒートマップ / 合格可能性スコアまで Q&A で明示、依存チケットなし → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ]に再編 + 主要 URL / 採用技術 / テスト方針 節を廃止 + 構築側メタ[Step 4 表現]除去 + テスト AC 追加、AC 4 件、波及は原因 + Q&A に集約)
- ✅ `B-A-03 / enrollment-04` 模試キャンセル後にタームが基礎タームに戻らない(2026-05-25 詳細化、Step 4 = `TermJudgementService` の `whereIn('status', [...])` に `'canceled'` を追加して判定対象を歪める、Advance 範囲 = Service のため「※」例外注記なし、依存チケットなし → **2026-05-28 規約刷新版で再生成**: Bug 新テンプレ[原因のみ]に再編 + 主要 URL / 採用技術 / テスト方針 節を廃止 + 構築側メタ[Step 4 表現]除去 + テスト AC 追加、AC 4 件[基礎/実践ターム判定 + 開始時実践化 + 不要 UPDATE スキップ + テスト])
- ✅ `T-B-02 / mentoring-05` コーチダッシュボードの担当受講生一覧 N+1 解消(2026-05-25 詳細化、Step 4 = `Dashboard\FetchCoachDashboardAction` の `->with(['user', 'certification'])` + `->withMax('learningSessions as last_activity_at', 'started_at')` を取り除く / Before 1+3N 本 → After 3 本 / 集約取得 + 複合 Eager Loading パターン、※ Action 内 → **2026-05-28 新 Task 構造で再生成**: 背景を概要へ統合 + やること/やらないこと→要件/スコープ外 + 実装方針を単一「変更内容」に集約 + AC 5→2 件[定数クエリ + テスト]に圧縮 + 振る舞い不変/Before-After を §3.5 に従い評価シート②へ)
- ✅ `T-B-03 / horizontal-01` `chunk()` / `chunkById()` / `cursor()` でメモリ最適化(2026-05-25 詳細化、Step 4 = 3 Schedule Command [`GraduateExpiredUsersCommand` / `FailExpiredEnrollmentsCommand` / `Mentoring\AutoCompleteMeetingsCommand`] の `->chunkById(100, ...)` を `->get(); foreach` に巻き戻し、各処理が WHERE 列を更新するため `chunkById()` 必須教材として配置、🕐 `cursor()` 用例は模範解答に存在せず概念教育で補完 → **2026-05-28 新 Task 構造で再生成**: 背景を概要へ統合 + 旧「やること・やらないこと」→「要件・スコープ外」+ AC 3 件にフラット化 + 実装方針を単一「変更内容」に集約 + 構築側メタ[Step 4 表現]除去 + Action クラス名を実装準拠に修正(`Plan\GraduateUserAction` / インライン `EnrollmentStatusChangeService` / `Meeting\AutoCompleteMeetingAction`))
- ✅ `T-A-01 / mock-exam-06` 模試マスタ一覧の N+1 解消(**2026-06-09 移設**: 旧 `mentoring-03`〔面談予約画面の N+1〕は `S-A-01`〔Google Calendar 連携〕依存だったため、依存なしで成立する模試マスタ一覧の N+1 へ移設。Step 4 = `MockExam\IndexAction` の `->with(['certification','createdBy','updatedBy'])->withCount('mockExamQuestions')` を外して 1+4N 化、citation = 新規 `tests/Feature/Http/MockExam/MockExamIndexQueryCountTest.php::test_index_query_count_does_not_grow_with_mock_exam_count`〔模範解答 PJ に追加。QaThread IndexTest の `DB::enableQueryLog`+`assertLessThan` パターン流用〕、AC 1 件、依存なし)
- ✅ `T-A-02 / mentoring-07` mentoring の Controller method を Action 分離(2026-05-25 詳細化、Step 4 = `MeetingController::store/cancel/upsertMemo` 3 method に Action 内ロジックを展開 + `Meeting\{Store,Cancel,UpsertMemo}Action.php` 3 ファイル削除、取得系 4 method [`index/show/fetchAvailability/indexAsCoach`] は対象外、`DB::transaction` + 具象例外 throw の責務分離を学ばせる、依存 `S-A-01` → **2026-05-28 新 Task 構造で再生成**: やること/やらないこと→要件/スコープ外 + 実装方針を単一「変更内容」に集約 + Before/After コード片を削除 + AC 8→3 件に圧縮 + 振る舞い不変/既存テスト pass を §3.5 に従い評価シート②へ)
- ✅ `T-A-03 / learning-01` 学習進捗の集計ロジックを Service へ集約(**2026-06-09 移設**: 旧 `mentoring-08`〔Google Calendar 連携を Service 分離〕は `S-A-01` / `T-A-02` 依存だったため、依存なしで成立する「散在した学習進捗集計の Service 集約」へ移設。Step 4 = `ProgressService`〔集約先〕をインライン化し 3 呼出元〔`EnrollmentController` / `Learning\ShowEnrollmentAction` / `Dashboard\FetchStudentDashboardAction`〕へ展開・Service 削除、`ProgressServiceTest` は受講生が再作成、AC 2 件〔集約のコード確認 + テスト〕、依存なし)
- ✅ `T-A-04 / horizontal-04` モックを用いたテスト追加(外部 API 連携)(2026-05-25 詳細化、Step 4 = `tests/Unit/Services/Google/{GoogleOAuthService,GoogleCalendarService}Test.php` / `tests/Unit/Repositories/GeminiLlmRepositoryTest.php` / `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php` を削除 or 正常系のみに削減 + `Http::preventStrayRequests()` / `#[Group('external')]` / HMAC 署名ヘルパー も剥がす、3 系統のモック手法 [Service = Mockery / Repository = `Http::fake` / Webhook = HMAC ヘルパー] の使い分けを学ばせる、依存 `S-A-01` / `S-A-02` / `S-A-03` / `T-A-03` → **2026-05-28 新 Task 構造で再生成**: 背景・目的/価値を概要へ統合 + AI 丸投げ耐性等の構築側メタ除去 + 主要URL・テスト方針・採用技術・改善対象コードメモを単一「変更内容」に集約 + AC 8→3 件 + ⚠️→✅ **模範解答 PJ ギャップ検出 → 対応完了**(commit `007063b` で Google Service 単体テスト 2 ファイル[OAuth 5 + Calendar 11 = 16 ケース]新設 + `Http::preventStrayRequests()` / `#[Group('external')]` 組込、Sail 16/16 pass 確認、_review-log に記録))
- ✅ `T-A-05 / horizontal-05` 通知・メール配信の非同期化(2026-05-30 新規作成: 同期実行 → `database` キューへ移行、`BaseNotification` + `InvitationMail` の `ShouldQueue`[既存]を活性化 + `$tries` / `backoff` 追加 + jobs migration 追加 + worker 運用、Scenario X[Basic は Queue 無縁、`S-B-04` / `S-B-09` / `S-B-10` と整合]、AC 3 件[非同期配信 / リトライ + 失敗ジョブ / テスト]、依存 `S-B-04` / `S-B-09`、模範解答 1723 テスト緑 + `database` キュー round-trip 検証済)
- ✅ `T-A-06 / dashboard-01` 管理者ダッシュボード集計のキャッシュ化(2026-05-31 新規作成: 重い admin 集計[全体 KPI / 資格別修了率]を、集計を所有する Service `EnrollmentStatsService` の `adminKpi()` / `completionRateByCertification()` で `Cache::remember`(キー・TTL は新規 `config/dashboard.php`、既定 300s)、受講状態遷移チョークポイント `EnrollmentStatusChangeService::recordStatusChange()` で `Cache::forget` による無効化 + TTL 失効のハイブリッド。既存 `DashboardArchitectureTest` の「dashboard UseCase で `Cache::` 禁止」に準拠しキャッシュは Service 層に配置(集約 Action は薄く維持)。同梱 `tests/Feature/UseCases/Dashboard/AdminDashboardCacheTest.php` 4 ケース[全体 KPI / 資格別修了率の 2 キー × キャッシュヒット / 状態遷移時無効化]、Sail 緑(dashboard 関連 52 + enrollment/certificate 系 248 pass)、依存なし。`T-B-01` と工数相殺[swap]、Broadcasting・DB インデックスは Advance 宣言から除外)
- ✅ `S-A-01 / mentoring-01` Google Calendar 連携(面談予約)(2026-05-25 詳細化、Advance 範囲 = Action / Service、新規 `coach_google_credentials` テーブル + `google_event_id` カラム追加 + `GoogleOAuthService` / `GoogleCalendarService` 2 分割 + `CoachGoogleCredential\{FetchAuthUrl,Store,Destroy}Action` + `Meeting\{Store,Cancel}Action` への組込み + `MeetingAvailabilityService` freebusy 統合 + トークン自動 refresh + API 失敗フォールバック、Step 4 = 全 GCal 関連コードを引き算で外す、依存チケットなし → **2026-05-28 新 Story 構造(実装方針 5 サブセクション + フラット AC)で再生成**: 概要を背景・目的へ統合 + やること/やらないこと→要件/スコープ外 + 実装方針を 5 サブセクションに再編 + AC 19→10 件にフラット化(規模大上限)+ テスト AC 追加 + **面談 Event の URL ソースを `meeting_url_snapshot` に訂正**(旧版 `users.meeting_url` 直参照は実装と不一致、予約時点スナップショットを Q&A に明記))
- ✅ `S-A-02 / ai-chat-01` Gemini AI チャットボット(2026-05-25 詳細化、Advance 範囲 = Repository / Action / Service、新規 `ai_chat_conversations` / `ai_chat_messages` テーブル + `LlmRepositoryInterface` + `GeminiLlmRepository` + `AiChatPromptBuilderService`(Section パンくず + default_enrollment 解決) + フローティングウィジェット + 同期メッセージ送信 + Transaction A 先行 commit + タイトル LLM 自動生成 + `throttle:ai-chat` 日次 50 通 + 機能 OFF スイッチ、Step 4 = 全 AI 関連コードを引き算で外す、依存チケットなし)
- ✅ `S-A-03 / meeting-quota-02` Stripe 連携(追加面談購入)(2026-05-25 詳細化、Advance 範囲 = Action / Middleware、新規 `payments` テーブル + `PaymentStatus` Enum + `CreateCheckoutSessionAction` + `StripeWebhook\HandleAction`(冪等性ガード + 3 イベント分岐)+ `PurchaseQuotaAction` + `MeetingQuotaCheckoutController` + `VerifyStripeSignature` Middleware + `MeetingQuotaPolicy::purchase` + Stripe SDK 採用、Step 4 = 全 Stripe 関連コードを引き算で外す、`meeting_quota_transactions` 基盤は提供 PJ 既存(消費 / 返却 / 管理者付与は提供 PJ 範囲)、依存 `S-B-02` → **2026-05-28 実装方針 5 サブセクション構造で再生成**、AC 18→10 件に圧縮(規模大上限)、やること/やらないこと→要件/スコープ外 改名 + 概要を背景・目的へ統合 + 独立 Seeder 設計節をデータモデル>初期データ Seeder へ統合(実 `PaymentSeeder` = 固定 student + demo×6 に事実修正)、Middleware エイリアス / CSRF 除外の所在を Laravel 10 構成(`app/Http/Kernel.php` の `$middlewareAliases` + `app/Http/Middleware/VerifyCsrfToken.php` の `$except`)に訂正(旧記述「bootstrap/app.php(Laravel 11+)」は本 PJ では誤り))
- ✅ `S-A-04 / certification-management-01` 修了証 PDF 出力(2026-05-25 詳細化 → **2026-05-28 実装方針 5 サブセクション構造で再生成**、AC 22→9 件に圧縮、Seeder 設計節をデータモデル節に統合、Advance 範囲 = Service / Action、`certificates` テーブルは提供 PJ 既存、本チケットで PDF 生成部分を追加 = `CertificatePdfService`(mpdf、A4 横向き、日本語 CJK)+ `Certificate\{Issue,Download}Action` + `CertificatePolicy::download`(admin 全件 / coach 担当資格 / student 本人)+ `resources/views/certificates/pdf.blade.php` + `active-learning` Middleware 非適用、Step 4 = PDF 生成 + DL エンドポイントを引き算で外す、依存チケットなし)
- ✅ `S-A-05 / notification-03` Sanctum Cookie 認証 + JS フロント通知表示(2026-05-25 詳細化 → 2026-05-26 規約刷新版で再生成 → **2026-05-28 実装方針 5 サブセクション構造で再々生成** → **2026-06-09 通知 JSON API 構築を統合**〔旧 `S-B-05` を吸収。`api.php` ルート 3 本 + Resource + API FormRequest の構築を本チケットが単独で担い Sanctum 認証 + JS フロントと一体化、依存を `S-B-05` から `S-B-04`〔通知基盤〕+ `S-B-09`〔お知らせ遷移先〕へ付け替え〕、AC 12 件、Advance 範囲 = Sanctum + 素の JS、Sanctum stateful 設定 + `auth:sanctum` を通知 API 全 3 ルートに適用 + CSRF Cookie endpoint 公開 + 認証ユーザー本人の通知に限定 + 通知ポップオーバー Blade + JS ポップオーバー制御(タブ切替 / 行クリック既読化 + 遷移 / 全件既読)+ TopBar バッジ動的更新、管理者は対象外、Step 4 = 通知 API + `auth:sanctum` + ポップオーバー JS + Blade を削除、依存 `S-B-04` / `S-B-09`)

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
