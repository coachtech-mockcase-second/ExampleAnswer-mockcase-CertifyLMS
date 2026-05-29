# Certify LMS 検証カバレッジ台帳

模範解答PJ の完璧性検証 = 将来の振る舞いベース採点オラクルのカバレッジ台帳。
各 Feature の「画面/フロー × ロール × ケース」を網羅列挙し、検証層を仕分ける。

## ケース種別（taxonomy）

| 種別 | 内容 |
|---|---|
| 正常 | ハッピーパス（主要操作が要件通り成功する）|
| 異常 | バリデーション違反 / 不正入力 / 404 / 状態不整合 / 競合 |
| 認可境界 | 他ロール・他人リソース・未ログイン・状態外（卒業生等）のアクセス可否 |
| 境界値 | 0件 / 上限 / 空状態 / 末尾ページ等のエッジ |
| 表示 | 静的レンダリング（崩れ無し / 必須要素の存在）|

## 検証層（layer）

| 層 | 意味 |
|---|---|
| `PHPUnit` | 既存 1702 テストで担保済（ロジック/認可/バリデーション/計算）→ **E2E では重複させない** |
| `E2E` | フルスタック / JS / UI / 通しジャーニーで実機検証（Playwright）|
| `両方` | PHPUnit で単体 + E2E で実機の両面から |

## 列定義

`ID | 画面/フロー | ロール | ケース | 期待する振る舞い（behavior-based） | 出所(REQ/NFR or チケットAC) | 層 | 状態`

状態凡例: ✅=spec 緑化済 / ⬜=未着手 / 🔁=要再確認

---

## 1. mock-exam ＜フォーマット見本（完全展開）＞

> 時間制限/タイマー/auto-submit は E-3(v3) で完全撤回済 → 検証対象外。

| ID | 画面/フロー | ロール | ケース | 期待する振る舞い | 出所 | 層 | 状態 |
|---|---|---|---|---|---|---|---|
| ME-01 | 模試ランディング(empty-state) | student | 表示 | 受講中資格が選択肢として並ぶ／受講ゼロ時は空状態＋資格カタログ導線 | catalog | E2E | ⬜ |
| ME-02 | 資格別カタログ | student | 表示 | 公開模試がカード表示、合格達成/進行中バッジ、問題数・合格点 | catalog | E2E | ⬜ |
| ME-03 | カタログ | student | 認可境界 | 他人の enrollment の catalog にアクセス不可（403/弾く） | Policy | 両方 | ⬜ |
| ME-04 | セッション開始(store) | student | 正常 | 「受験を始める」でセッション生成 → lobby へ | sequence | E2E | ⬜ |
| ME-05 | セッション開始(store) | student | 異常 | 進行中セッションがある模試で重複開始しない（再開へ誘導） | StoreAction ガード | 両方 | ⬜ |
| ME-06 | lobby | student | 表示 | 問題数・合格点・「時間制限なし」・開始/キャンセルが出る | lobby | E2E | ⬜ |
| ME-07 | 開始(start) | student | 正常 | 「受験を開始する」で InProgress 化 → take 画面へ | StartAction | E2E | ⬜ |
| ME-08 | take 自動保存 | student | 正常 | 選択肢選択→PATCH 200→「自動保存済」表示＋解答済カウント更新 | answer-autosave | E2E | ✅ |
| ME-09 | take 中断再開 | student | 正常 | 離脱後にカタログ「受験を再開」→ 解答済が保持されている | design(再開) | E2E | ⬜ |
| ME-10 | 提出(submit) | student | 正常 | confirm 承認→採点→結果画面（合否・得点率・正誤） | Submit/GradeAction | E2E | ⬜ |
| ME-11 | 採点結果(合格) | student | 表示 | 「合格点 突破」・得点率・弱点ヒートマップ・合格可能性 | result | E2E | ✅ |
| ME-12 | 採点結果(不合格) | student | 表示 | 「合格点未達」・弱点分野ドリル導線が出る | result | E2E | ✅ |
| ME-13 | 受験履歴 index | student | 表示 | 採点完了/キャンセル済が一覧、合否・得点率・結果リンク | index | E2E | ✅ |
| ME-14 | 受験履歴 フィルタ | student | 正常 | 合格のみ/不合格のみ/資格IDで絞り込みできる | index filter | E2E | ⬜ |
| ME-15 | セッション/結果 | student | 認可境界 | 他人のセッションの take/result にアクセス不可 | MockExamSessionPolicy | 両方 | ⬜ |
| ME-16 | 受験全般 | guest | 認可境界 | 未ログインはログインへリダイレクト | auth ミドルウェア | E2E | ✅ |
| ME-17 | 受験 | student(失敗/卒業) | 認可境界 | learning/passed 以外の enrollment では受験不可（EnsureActiveLearning） | middleware | 両方 | ⬜ |
| ME-18 | 模試 CRUD | admin | 正常 | 模試の作成/編集/公開/非公開/削除ができる | management | 両方 | ⬜ |
| ME-19 | 模試 CRUD | coach/student | 認可境界 | 管理操作は admin 以外不可 | MockExamPolicy | 両方 | ⬜ |
| ME-20 | 設問 CRUD | admin | 正常/異常 | 設問・選択肢の追加/編集、正答1件必須等のバリデーション | management | 両方 | ⬜ |
| ME-21 | セッション監視 | admin | 表示 | 受講生のセッション一覧/詳細を閲覧（フィルタ） | management/monitor | E2E | ⬜ |

> mock-exam は E2E 対象 16 項目中 5 項目が緑化済（✅）。残り 11 を Phase2b で展開。
> `両方` 項目は PHPUnit 側の該当テスト名も後で紐付ける（重複回避の根拠を明示）。

---

## 全18 Feature インデックス（残り17は並列抽出で同形式に展開）

各 Feature の spec(requirements/design) の REQ/NFR + 要件シート100% の該当チケット受け入れ条件 + routes/Blade から、上記フォーマットで検証項目を抽出する。

| # | Feature | 主な画面/フロー | 主ロール | 状態 |
|---|---|---|---|---|
| 1 | mock-exam | カタログ/受験/採点/履歴/管理 | student/admin | ◐ 見本作成済 |
| 2 | auth | ログイン/ログアウト/招待オンボーディング/パスワードリセット | 全 | ⬜（login 3件✅） |
| 3 | user-management | 招待/一覧/詳細/退会/コース延長/面談枠付与 | admin | ⬜ |
| 4 | certification-management | 資格カタログ/CRUD/公開/カテゴリ/コーチ割当 | admin/student | ⬜ |
| 5 | content-management | Part/Chapter/Section/設問 CRUD/公開/並替 | admin | ⬜ |
| 6 | enrollment | 受講登録/一覧/詳細/目標/メモ/修了証受取 | student/coach/admin | ⬜ |
| 7 | learning | 教材閲覧/進捗/学習時間目標/検索 | student | ⬜ |
| 8 | quiz-answering | Section演習/ドリル/履歴/統計/弱点 | student | ⬜ |
| 9 | mentoring | 面談予約/キャンセル/メモ/コーチ空き枠/Google連携 | student/coach | ⬜（外部API含む） |
| 10 | chat | チャットルーム/メッセージ/リアルタイム(Pusher) | student/coach/admin | ⬜（外部API含む） |
| 11 | qa-board | スレッド/返信/解決/モデレーション | student/coach/admin | ⬜ |
| 12 | notification | 通知一覧/既読/通知API(Sanctum)/バッジ | 全 | ⬜（Advance API） |
| 13 | dashboard | ロール別ダッシュボード(student/coach/admin/卒業生) | 全 | ⬜ |
| 14 | ai-chat | AI会話/メッセージ/再試行/タイトル生成(Gemini) | student | ⬜（外部API含む） |
| 15 | settings-profile | プロフィール/アバター/パスワード/空き枠/Google資格情報 | 全 | ⬜ |
| 16 | plan-management | プラン CRUD/コース延長/期限/履歴/決済(Stripe) | admin/student | ⬜（外部API含む） |
| 17 | meeting-quota | 面談枠購入(Stripe)/付与/返金/履歴 | student/admin | ⬜（外部API含む） |
| 18 | default-enrollment | 既定受講登録の解決/切替 | student | ⬜ |

> 外部API（Google/Pusher/Gemini/Stripe）を含む Feature は、貴方が用意する実API環境（OAuth連携・`stripe listen` 等）の上で Phase3 と連動して検証する。
