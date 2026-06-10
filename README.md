# Certify LMS — 模擬案件②

> COACHTECH 模擬案件②「**Certify LMS**」の **要件・模範解答・評価基準** を収めたリポジトリ。採点者とコーチが、案件内容と評価基準を把握するための情報源。

マルチ資格対応の資格取得 LMS「**Certify LMS**」を題材に、受講生が「**既存プロジェクトへの参画**」を体験する模擬案件。受講生は別途公開される Template リポジトリ（`AssignedProject-mockcase-CertifyLMS`）を自分のリポジトリに複製して作業し、本リポジトリはその **模範解答・チケット要件・採点基準** を収録する。

---

## 模擬案件② の設計意図

新規構築型の課題（確認テスト ContactForm / 模擬案件① BookShelf）では扱えなかった以下 3 課題への解として設計される。

| # | 受講生の課題 | 本案件のアプローチ |
|---|---|---|
| 1 | 既存 PJ 参画の経験不足 | 既存コードを読んで改修する構成（コードリーディング前提）|
| 2 | 要件ヒアリングの経験不足 | 受講生には 30% の曖昧な要件のみ渡し、コーチ（PM 役）へのヒアリングで詳細化させる |
| 3 | AI 丸投げによる理解なき実装 | チケットの曖昧化 + PR 5 セクション記述の必須化 + Advance なしでは S を取れない配点 |

---

## プロダクト「Certify LMS」

**プラン受講型** の資格取得 LMS。受講生はプランを購入し、管理者の招待でログインしてプラン期間内に複数資格を並行学習する。目標受験日と合格点ゴールを設定し、問題演習を中心に苦手分野を克服していく学習設計。

| 項目 | 内容 |
|---|---|
| ロール | 受講生（student） / コーチ（coach） / 管理者（admin） |
| 機能数 | 18 領域 |
| 想定学習時間 | 約 222 時間（全 41 チケットの目安工数合計）|
| 評価ライン | 取得率 **60% = A** / **80% = S**（S 到達には Advance での得点が必須）|

### 主要機能

| ドメイン | 機能 |
|---|---|
| 認証・ユーザー | 招待オンボーディング（`auth`）/ 管理者のユーザー管理（`user-management`）/ 設定・プロフィール（`settings-profile`）|
| 学習コンテンツ | 資格マスタ（`certification-management`）/ 教材 Part・Chapter・Section（`content-management`）/ 教材閲覧（`learning`）/ 演習問題（`quiz-answering`）/ 模試・採点・弱点分析（`mock-exam`）|
| 受講管理 | プラン（`plan-management`）/ 受講登録・目標受験日・個人目標（`enrollment`）/ 既定受講（`default-enrollment`）|
| メンタリング | 面談予約・受講生メモ（`mentoring`）/ 面談パック・追加購入（`meeting-quota`）/ 受講生 ↔ コーチ チャット（`chat`）/ 質問掲示板（`qa-board`）|
| 横断 | 通知 基盤 + JSON API（`notification`）/ 3 ロール別ダッシュボード（`dashboard`）/ Gemini AI 学習相談（`ai-chat`）|

---

## チケット構成（全 41 件）

受講生はチケット単位で実装し、1 チケット = 1 PR で提出する。難易度は **Basic**（教材・既習範囲内）と **Advance**（教材範囲外の新規技術）に分かれる。

| 種別 | サブカテゴリ（件数）| Basic | Advance | 計 |
|---|---|---:|---:|---:|
| **Story**（機能開発）| 新規機能の構築 8 / 既存機能の拡張 6 | 9 | 5 | 14 |
| **Bug**（バグ修正）| 認可・認証 5 / データ・計算・表示 13 / 並行性 1 | 16 | 3 | 19 |
| **Task**（リファクタ・既存改修）| パフォーマンス 5 / リファクタリング 3 | 2 | 6 | 8 |
| **計** | | **27** | **14** | **41** |

- 各チケットの詳細（受け入れ条件・実装方針・想定 Q&A）は [`関連ドキュメント/要件シート_詳細度100%/`](./関連ドキュメント/要件シート_詳細度100%/) に 1 チケット = 1 ファイルで収録。**コーチはこの実装方針・想定 Q&A を見て受講生のヒアリングに応答する**
- PR 記述は **5 セクション必須**（概要 / 調査・設計判断 / 実装内容 / 自動テスト / 動作確認）。雛形は提供プロジェクトの `.github/PULL_REQUEST_TEMPLATE.md` として配布
- 動的機能（タイマー / 状態遷移 / リアルタイム / モーダル / 非同期更新）の動作確認は **動画必須**、静的 UI はスクショ、バグ修正は修正前後の比較

---

## 評価設計

全体配点に対する **取得率** で判定する（**60% 以上 = A** / **80% 以上 = S**）。Basic を完璧にこなせば A には届くが、S 到達には Advance での得点が必須（AI 丸投げ耐性の中核）。

### 評価シートの 2 大項目

| 大項目 | 中身 | 配点比（目安）|
|---|---|---:|
| **チケット要件** | 各チケットの受け入れ条件を 1 項目 = 1 採点行に展開。採点者は各 PR を開き、振る舞いベース（要件通り動くか）で判定 | 約 75% |
| **横断品質** | コード品質（命名 / N+1 の新規混入なし / 既存パターン踏襲 / 外部 API キーの `.env` 管理）+ テスト品質（全 pass / カバレッジ）+ README への新規設定追記 + PR 5 セクション記述の全体達成度 | 約 25% |

### 難易度別の配点

| 難易度 | 配点比（目安）| 設計意図 |
|---|---:|---|
| **Basic** | 約 65% | 下限 60% で「教材範囲を真面目にこなせば A を保証」、上限 80% で「Advance なしでは S 不可」を担保 |
| **Advance** | 約 35% | S（80%）到達には Advance での得点が必須 |

採点は受け入れ条件レベルの **振る舞いベース**（実装方法は問わない）。横断品質のみ、振る舞いに依らない最低限の品質安全網として配点を抑えて配置する。採点行の実体は `関連ドキュメント/評価シート.md` に収録。

---

## 主要技術スタック

| 領域 | 採用技術 |
|---|---|
| Backend | PHP 8.5 / Laravel 10 / MySQL 8.4 / Eloquent |
| Frontend | Blade + Tailwind CSS + 素の JavaScript（Vite ビルド）|
| 認証 | Laravel Fortify（Web セッション）+ Laravel Sanctum SPA Cookie 認証（通知 API + JS フロント）|
| 外部 API | Google Calendar OAuth（面談予約）/ Gemini API（AI 学習相談）/ Stripe（追加面談購入）|
| リアルタイム / 非同期 | Pusher Broadcasting（チャット）/ Laravel Queue + Job（通知・メールの非同期化）|
| PDF 生成 | mpdf（修了証 PDF、A4 横向き・日本語対応）|
| 開発環境 | Docker / Laravel Sail |

> **採点対象の Advance スコープ**は、Google Calendar 連携 / Gemini AI チャット / Stripe 決済 / 修了証 PDF / Sanctum + JS 通知 / キュー非同期化 / キャッシュ化、および Advance のバグ・パフォーマンス改善チケット。**リアルタイムチャット（Pusher）は完成状態で提供される**ため、受講生はコードを読むのみで「構築」チケットは持たない。

---

## ローカル環境構築（模範解答の起動）

`模範解答プロジェクト/` は全 41 チケットを実装し終えた完成版。**正しい動作の基準** として起動し、PR の検証や案件の動作確認に使える。

### 前提

- Docker / Docker Compose

> Apple Silicon (M1/M2/M3) Mac で `sail up` がプラットフォームエラーになる場合は `模範解答プロジェクト/compose.yaml` の各サービスに `platform: linux/amd64` を追加する。

### 手順

1. **クローン → 模範解答プロジェクトに移動**

   ```bash
   git clone git@github.com:coachtech-mockcase-second/ExampleAnswer-mockcase-CertifyLMS.git
   cd ExampleAnswer-mockcase-CertifyLMS/模範解答プロジェクト
   ```

2. **`.env` 準備**

   ```bash
   cp .env.example .env
   ```

   外部 API（Google Calendar / Gemini / Stripe / Pusher）のキーは **空のままで Basic 機能は動作する**。Advance 機能（面談予約の Google Calendar 連携 / AI 学習相談 / 追加面談購入 / リアルタイムチャット）を試す場合のみ各キーを設定する。

3. **Composer install（初回のみ Docker 直叩き、`vendor/` が無いため Sail を使えない）**

   ```bash
   docker run --rm \
       -u "$(id -u):$(id -g)" \
       -v "$(pwd):/var/www/html" \
       -w /var/www/html \
       laravelsail/php85-composer:latest \
       composer install --ignore-platform-reqs
   ```

4. **Sail 起動**

   ```bash
   ./vendor/bin/sail up -d
   ```

   > エイリアス推奨: `alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'`（以降 `sail` で呼べる）

5. **アプリキー生成 + storage リンク + DB 初期化（Seeder 込み）**

   ```bash
   sail artisan key:generate
   sail artisan storage:link
   sail artisan migrate:fresh --seed
   ```

   `storage:link` は教材画像・プロフィール画像の配信に必要。`DatabaseSeeder` が全 Seeder（User / Plan / Certification / Content / MockExam / Chat / Notification 等）をまとめて投入する。

6. **フロントビルド**

   ```bash
   sail npm install
   sail npm run build
   ```

   > Blade / CSS / JS を編集しながら確認する場合は、`build` の代わりに `sail npm run dev` を起動したままにする（Vite ホットリロード）。

7. **（任意）キューワーカー起動** — 通知・メールの実際の配信を確認したい場合は別ターミナルで起動する。

   ```bash
   sail artisan queue:work
   ```

8. **アクセス**

   | サービス | URL |
   |---|---|
   | アプリ | http://localhost:8000 |
   | phpMyAdmin | http://localhost:8080 |
   | Mailpit（送信メール確認）| http://localhost:8025 |

### 初期ユーザー

`UserSeeder` が固定アカウントを生成（パスワードはすべて `password`）。

| ロール | Email |
|---|---|
| 管理者（admin）| `admin@certify-lms.test` |
| コーチ（coach）| `coach@certify-lms.test` / `coach2@certify-lms.test` |
| 受講生（student）| `student@certify-lms.test` |

状態網羅用の demo ユーザー（招待中 / 学習中 / 修了 / 退会など）も生成され、ステータスごとの画面・権限差分を確認できる。

### テスト実行

```bash
sail artisan test
```

---

## リポジトリの主なディレクトリ

| パス | 内容 |
|---|---|
| [`関連ドキュメント/要件シート_詳細度100%/`](./関連ドキュメント/要件シート_詳細度100%/) | 全 41 チケットの詳細（受け入れ条件・実装方針・想定 Q&A）。**コーチのヒアリング応答用の一次資料**（受け入れ条件は評価シートの採点行の元） |
| `関連ドキュメント/評価シート.md` | 採点シート（受け入れ条件を採点行に展開）|
| `関連ドキュメント/要件シート_詳細度30%/` | 受講生に配布される抽象化版の要件シート（課題ガイド `概要.md` 込み、AssignedProject にコピー）|
| `模範解答プロジェクト/` | 全 41 チケットを実装し終えた完成版（正しい動作の基準）|
| `提供プロジェクト/` | 受講生が Template として受け取る初期状態の Laravel プロジェクト（README / ONBOARDING / PR テンプレート同梱）|

---

## 関連リポジトリ

| リポジトリ | 用途 | 公開状態 |
|---|---|---|
| **`ExampleAnswer-mockcase-CertifyLMS`**（本リポ）| 要件・模範解答・評価基準の収録 | 非公開 |
| `AssignedProject-mockcase-CertifyLMS` | 受講生 Template（GitHub Template Repository）| 公開 |

> 受講生は AssignedProject の "Use this template" で自分の GitHub リポジトリを生成し、各チケットを `feature/{ticket-id}` ブランチ → `basic`（Advance フェーズは `advance`）への PR として提出する。
