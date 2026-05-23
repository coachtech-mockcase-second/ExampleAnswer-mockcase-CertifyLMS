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

### ⚠️ 通知発火を `notification` Feature(S-B-05)に移管

- **判定**: ⚠️ 修正必要(Phase D で模範解答 PJ も再整理)
- **背景**: S-B-01 書き直し時、ユーザー判断で「回答時の通知発火」を本チケットのスコープ外とした。本来 qa-board 側で発火呼び出しを書く実装になっていたが、`notification`(S-B-05)のスコープに移管する。
- **影響範囲**(Phase D 一括処理):
  - 模範解答 PJ で qa-board 側の Action から `Notification::send()` 呼び出しを削除し、`notification` Feature 側のラッパー or イベント駆動構成に整理
  - 関連テスト・Q&A の所在見直し
- **本チケット側**: 依存チケット `S-B-05` も解除済み(本チケット内では通知関連を一切扱わない)、受け入れ条件 / やること / 実装方針 / Q&A から全削除済み
