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
| Policy 三重判定(ロール + 当事者 + 担当資格) | ✅ 適切 | `QaThreadPolicy::view` で coach の `coachingCertificationIds()` チェック、`delete` で「投稿者本人 + 回答 0 件 OR admin」の三重判定 |
| status Enum + resolved_at 二本立て + 同時更新 | ✅ 適切 | 他 Feature(`Enrollment.status + passed_at` 等)と整合、`tech.md` 規約準拠 |
| 通知発火位置(StoreAction → 通知ラッパー経由) | ✅ 適切 | 自己回答スキップを notification 側に寄せる責務分担は REQ-qa-board-111 と一致 |
| 列挙攻撃防御(担当外資格 → 403) | ✅ 適切 | NFR-qa-board-006 通り、403 で「担当外であることを明示」 |
| **Controller 命名揺らぎ** | 🕐 Phase 7 | spec = `Admin\QaThreadController` / 実装 = `QaThreadModerationController`。Feature 全体で命名規約統一が必要、他 Feature も横断調査して規約確定 |
| **View ディレクトリ名揺らぎ** | 🕐 Phase 7 | Feature 名 / URL = `qa-board` だが View = `qa-thread/`。同様に Phase 7 で全 Feature 横断確認後リネーム |

---

## Phase 7 で扱う横断課題まとめ

詳細化中に「他 Feature との整合性が必要」と判定された課題。Phase 7(全件詳細化完了後)で一括処理する。

| # | 課題 | 関連チケット | 対応方針 |
|---|---|---|---|
| 1 | Controller 命名規約(`Admin\` サブディレクトリ vs `Moderation` 接尾辞) | S-B-01 ほか admin 機能を持つ全 Feature | 全 18 Feature の Controller を横断調査 → 規約確定 → リネーム |
| 2 | View ディレクトリ名規約(Feature 名 vs エンティティ名) | S-B-01 ほか | 全 18 Feature の `resources/views/` を横断調査 → 規約確定 → リネーム |

---

## S-B-01 / qa-board-01 追加レビュー(2026-05-23、書き直し時)

### ⚠️ サイドバーバッジ機能の廃止 → 模範解答 PJ から削除

- **判定**: ⚠️ 修正必要(Phase D で模範解答 PJ も削除)
- **背景**: S-B-01 書き直し時、ユーザー判断で「コーチ用サイドバーバッジ(担当資格 × 未解決 × 回答 0 件 の件数表示)」を本チケットのスコープから除外。採点上の重要度が低く、機能としても廃止する判断。
- **影響範囲**(Phase D 一括処理):
  - `app/View/Composers/SidebarBadgeComposer.php` の coach 分岐から qa-board 関連の COUNT クエリ削除
  - `resources/views/components/sidebar.blade.php`(または相当する Blade)から「質問対応 (N)」バッジ表示削除
  - 関連テストがあれば削除
- **本チケット側**: 受け入れ条件 / 実装方針 / Q&A から全削除済み、やらないことに明示

### ⚠️ 通知発火を `notification` Feature(S-B-04)に移管

- **判定**: ⚠️ 修正必要(Phase D で模範解答 PJ も再整理)
- **背景**: S-B-01 書き直し時、ユーザー判断で「回答時の通知発火」を本チケットのスコープ外とした。本来 qa-board 側で発火呼び出しを書く実装になっていたが、`notification`(S-B-04)のスコープに移管する。
- **影響範囲**(Phase D 一括処理):
  - 模範解答 PJ で qa-board 側の Action から `Notification::send()` 呼び出しを削除し、`notification` Feature 側のラッパー or イベント駆動構成に整理
  - 関連テスト・Q&A の所在見直し
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
| 削除制約(公開中ガード) | ✅ 適切 | `DestroyAction` 内で公開中なら `MeetingPackNotDeletableException`(409)、下書き / アーカイブのみ SoftDelete。`Handler` の HTML→ redirect+flash 変換も活用 |
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
