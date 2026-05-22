# Certify LMS 100% 要件シート

## 位置づけ

**100% 版要件シート本体**(採点者・コーチ向け、ただし両者は別ロール)。1 チケット = 1 .md ファイルとして管理し、本シートを正として **評価シート** と **30% 版要件シート** を派生生成する(本シートが Single Source of Truth)。

| ロール | 責務 | 本シートでの主な参照箇所 |
|---|---|---|
| **採点者** | PR を確認して受け入れ条件を判定 → 評価シートに採点を記入 | 各チケットの **受け入れ条件** セクション |
| **コーチ**(PM 役) | 受講生からのヒアリングに応答(30% 版から 100% 版相当の情報を引き出す手助け) | 各チケット下半分の **実装方針(参考)** + **補足** セクション |

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
└── Task/                  # 7 件(Basic 3 / Advance 4)
```

**合計 40 件 / 目標 225h ± 10%**。Basic / Advance はファイル名(`S-B-XX` / `S-A-XX` 等)で識別する(サブディレクトリで階層化しない)。

> テンプレ(`story.md` / `bug.md` / `task.md`)は `.claude/skills/ticket-detail-100p/templates/` 配下。本ディレクトリには配置しない。

## ファイル命名規則

`{チケットID}_{Feature略称-連番}.md`(例: `S-B-01_qa-board-01.md` / `B-B-09_content-management-04.md`)。`B` = Basic / `A` = Advance。

## 採点者の使い方

**採点者** が本シートで各チケットを評価する標準フロー:

1. 該当チケットの **PR を確認**(file Changes で実装内容、画面動作で振る舞い)
2. 本シート対象チケット(`Story/{ID}.md` 等)の **受け入れ条件** をチェックリスト形式で 1 項目ずつ判定
3. 受け入れ条件 = 評価シート ① の 1 採点行 と 1:1 対応(採点シートに転記)
4. 横断採点(② コード品質 / ③ ドキュメント)は評価シート側で行う(チケット単位の作業ではない)

> 受講生からのヒアリング応答は **コーチ** の役割(本シートの **実装方針(参考)** + **補足** セクションを参照)。採点者は通常これらのセクションを見ない。

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

- **Basic → Advance**: Advance は Basic 上に積む拡張(例: S-A-06 Sanctum は S-B-06 認証なし API の上に後付け)。Basic 確定後に Advance を詳細化するほうが整合が取りやすい
- **Story → Bug → Task**: 記述スタイルが異なる(特に Bug は実装方針なし)ため、種別単位で進める

## チケット一覧(40 件)

> 出所: `../要件ブレインストーミング.md` § 2(2026-05-21 草案 fix)。Skill 実行時の入力情報源として使用。

### Story Basic(9 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `S-B-01` | `qa-board-01` | 質問掲示板の実装 | 新規機能の構築 | 13h |
| `S-B-03` | `meeting-quota-01` | 面談パックマスタ管理(admin マスタ CRUD) | 既存機能の拡張 | 6h |
| `S-B-04` | `plan-management-01` | プラン管理 Admin マスタ UI | 既存機能の拡張 | 6h |
| `S-B-05` | `notification-01` | 通知(Laravel Notification、DB + Mail) | 新規機能の構築 | 12h |
| `S-B-06` | `notification-02` | 通知 JSON API(認証なし) | 新規機能の構築 | 6h |
| `S-B-07` | `enrollment-03` | 個人目標(EnrollmentGoal)CRUD | 新規機能の構築 | 8h |
| `S-B-08` | `settings-profile-01` | 設定・プロフィール画面 | 既存機能の拡張 | 7h |
| `S-B-09` | `mentoring-06` | コーチ用 受講生メモ(EnrollmentNote)編集 | 新規機能の構築 | 6h |
| `S-B-10` | `notification-05` | admin お知らせ配信機能 | 新規機能の構築 | 8h |

### Story Advance(5 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 |
|---|---|---|---|---|
| `S-A-02` | `mentoring-01` | Google Calendar 連携(面談予約) | 既存機能の拡張 | 16.5h |
| `S-A-03` | `ai-chat-01` | Gemini AI チャットボット | 新規機能の構築 | 12h |
| `S-A-04` | `meeting-quota-02` | Stripe 連携(追加面談購入) | 新規機能の構築 | 16h |
| `S-A-05` | `certification-management-01` | 修了証 PDF 出力 | 既存機能の拡張 | 6h |
| `S-A-06` | `notification-03` | Sanctum Cookie 認証追加 + JS フロント通知表示 | 既存機能の拡張 | 13.5h |

### Bug Basic(16 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 | Step 4 仕込み方 |
|---|---|---|---|---|---|
| `B-B-01` | `content-management-01` | コーチ教材アクセス 403 → 編集可能化 | 認可・認証 | 4h | コーチ Policy で `denied` を返す |
| `B-B-02` | `content-management-02` | 教材一覧 ソート順無視 | 機能(ソート) | 2.5h | Controller の `orderBy('order')` 追加忘れ |
| `B-B-03` | `content-management-03` | 教材一覧で archived 資格も表示 | データ(クエリ漏れ) | 3h | Controller の `where('status', 'published')` 条件漏れ |
| `B-B-04` | `user-management-02` | admin 受講生一覧で withdrawn も表示 | データ(クエリ漏れ) | 3h | Controller の `where('status', '!=', 'withdrawn')` 条件漏れ |
| `B-B-05` | `user-management-03` | ユーザー招待で重複 email 可能 | データ(バリデーション) | 2.5h | FormRequest の `unique:users,email` ルール漏れ |
| `B-B-06` | `auth-01` | 招待トークン使い回し可能 | セキュリティ(トークン期限管理) | 3h | Controller / Action で `used_at` UPDATE 忘れ + `expires_at >= now()` を `>` 境界ミス |
| `B-B-07` | `quiz-answering-01` | 解答後フラッシュメッセージ表示忘れ | UI/UX(フラッシュ) | 2h | Controller の `redirect()->with('success', ...)` 漏れ |
| `B-B-08` | `settings-profile-02` | 設定保存後のリダイレクト先誤り | UI/UX(リダイレクト) | 2h | Controller の `redirect()` 先誤り |
| `B-B-09` | `content-management-04` | 受講生が未登録資格の教材詳細を直叩き閲覧可 | 認可・認証(IDOR) | 3h | Controller の `$this->authorize()` 漏れ |
| `B-B-10` | `meeting-quota-04` | 面談キャンセル時に残数が返却されない | データ(Tx 漏れ) | 3h | `CancelMeetingAction` 内で `MeetingQuotaTransaction.refunded` INSERT 漏れ(※Basic 範囲外、Skill 生成時に「※」注記) |
| `B-B-11` | `user-management-04` | 招待中ユーザー一覧で誤った status 条件 | データ(クエリ条件誤り) | 3h | Controller の where 条件を誤ったコピペ |
| `B-B-12` | `auth-02` | オンボーディング完了時の status 遷移漏れ | 機能(状態遷移) | 3h | Controller の `$user->update(['status' => UserStatus::InProgress])` 行を削除 |
| `B-B-13` | `auth-03` | オンボーディングで `password_confirmation` 漏れ | データ(バリデーション) | 2h | FormRequest の `'password' => 'confirmed'` ルールを抜く |
| `B-B-14` | `chat-01` | chat 未読バッジで自分の発言もカウント | データ(クエリ条件誤り) | 3h | Controller の未読集計 query で `where('sender_id', '!=', auth()->id())` 漏れ |
| `B-B-15` | `auth-04` | withdrawn ユーザーがログイン可能 | 認可・認証(認証チェック漏れ) | 3h | Fortify の `AuthenticateUser` に status チェックを書かない |
| `B-B-16` | `auth-05` | graduated ユーザーがプラン機能アクセス可 | 認可・認証(Middleware チェック漏れ) | 3h | 既存 `EnsureActiveLearning` Middleware から status 判定行を削除 |

### Bug Advance(3 件)

| ID | Feature 連番 | タイトル | サブカテゴリ | 工数 | Step 4 仕込み方 |
|---|---|---|---|---|---|
| `B-A-01` | `mentoring-02` | 面談予約の悲観ロックバグ | 並行性 | 6h | `lockForUpdate()` 無しで実装、同時リクエストで重複作成 |
| `B-A-02` | `mock-exam-04` | 模試採点ロジックバグ | 機能(計算) | 8h | `ScoringAction` / `ScoringService` の採点計算ロジックを意図的に壊す |
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
| Story | 1 / 9 | 0 / 5 | 1 / 14 |
| Bug | 0 / 16 | 0 / 3 | 0 / 19 |
| Task | 0 / 3 | 0 / 4 | 0 / 7 |
| **計** | **1 / 28** | **0 / 12** | **1 / 40** |

### 完成済み

- ✅ `S-B-01 / qa-board-01` 質問掲示板の実装(2026-05-22 初版 → 同日 新テンプレ整合版に再点検済、※ 振る舞いと文言の分離方針に基づく 3 回目再点検を Skill 完成後に予定)

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
