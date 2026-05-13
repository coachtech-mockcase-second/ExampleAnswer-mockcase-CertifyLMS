---
paths:
  - "提供プロジェクト/resources/views/layouts/**"
  - "提供プロジェクト/resources/views/errors/**"
  - "提供プロジェクト/resources/css/**"
  - "提供プロジェクト/resources/js/components/**"
  - "提供プロジェクト/tailwind.config.js"
  - "模範解答プロジェクト/resources/views/layouts/**"
  - "模範解答プロジェクト/resources/views/errors/**"
  - "模範解答プロジェクト/resources/css/**"
  - "模範解答プロジェクト/resources/js/components/**"
  - "模範解答プロジェクト/tailwind.config.js"
---

# UI Foundation 規約

> アプリケーション全体の UI 構造（サイドバー / 共通画面責務 / Wave 0a/0b プロセス / デザイントークン）の契約定義。
> Blade 文法規約・共通コンポーネント API は [frontend-blade.md](./frontend-blade.md) を参照。
> Tailwind 個別スタイリング規約は [frontend-tailwind.md](./frontend-tailwind.md) を参照。

## このドキュメントの役割

| タイミング | 使い方 |
|---|---|
| **Wave 0a 着手前**（Claude Design Web UI、別環境） | 本ドキュメント「Wave 0a への指示書」を読み込み、Claude Design へ Design System + Hero Screens の要件として渡す |
| **Wave 0b 着手前**（Claude Code 主セッション） | 本ドキュメント「Wave 0b の完成判定基準」を読み込み、実装完了の Definition of Done として使う |
| **Feature spec 執筆時** | 本ドキュメント「サイドバー構造」「ロール共通画面の責務分担」を読み込み、所有 Feature を確定する |
| **Feature 実装時** | 本ドキュメント「アクセシビリティ要件」「レスポンシブブレークポイント」を読み込み、各画面の品質基準とする |

## 設計原則

- **Tailwind UI / shadcn/ui の命名規則に準拠**: variant / size / type / disabled 等の Prop 名を業界標準に揃える（詳細 API は [frontend-blade.md](./frontend-blade.md)）
- **Heroicons v2（outline / solid）を標準アイコンセット**: 無料 / Tailwind 公式 / Laravel コミュニティ標準。Blade では `blade-ui-kit/blade-heroicons` を Wave 0b で導入
- **Mobile-first レスポンシブ**: Tailwind の `sm:` / `md:` / `lg:` / `xl:` プレフィックスで段階拡張
- **ダークモード非対応**: 教育PJスコープ外（[tech.md](../../docs/steering/tech.md) 準拠）
- **Alpine.js / Livewire 不採用**: インタラクションは素の JS（[frontend-javascript.md](./frontend-javascript.md)）
- **アクセシビリティ WCAG 2.1 AA 相当**: 後述「アクセシビリティ要件」参照
- **Server-rendered first**: Blade レンダリング前提、JS は最小限のインタラクションのみ

## アプリケーションシェル

### レイアウト構造

#### `layouts/app.blade.php`（認証後）

```
┌────────────────────────────────────────────────────────────┐
│ TopBar: ロゴ            [通知ベル(badge)] [ユーザー▼]         │
├──────────────┬─────────────────────────────────────────────┤
│              │                                             │
│   Sidebar    │   Main Content (max-w-7xl mx-auto p-4 lg:p-8)│
│  (lg: 固定 w-64) │                                          │
│  (sm: drawer)│   <x-flash />                                │
│              │   @yield('content')                          │
│              │                                             │
└──────────────┴─────────────────────────────────────────────┘
```

実装の骨子は [frontend-blade.md](./frontend-blade.md) の「共通レイアウト」セクション参照。

#### `layouts/guest.blade.php`（未ログイン）

ロゴ中央 + フォームカード中央。サイドバー無し。ログイン / オンボーディング / パスワードリセット / エラーページが継承。

#### `layouts/pdf.blade.php`（dompdf 専用）

`app.blade.php` を継承せず、インライン `<style>` のみ。日本語フォント `IPAGothic` 指定。

## サイドバー構造（ロール別）

サイドバーは **ロール別の独立 Blade** として `resources/views/layouts/_partials/sidebar-{role}.blade.php` に配置。`layouts/app.blade.php` 内で `@include('layouts._partials.sidebar-' . auth()->user()->role->value)` で切替。

### admin（管理者）

```
┌─────────────────────────────────┐
│ ダッシュボード         [home]    │ → dashboard.index
├─────────────────────────────────┤
│ 運用                            │
│ ユーザー管理         [users]    │ → admin.users.index
│ 資格マスタ管理 [academic-cap]   │ → admin.certifications.index
│ カテゴリ管理         [tag]      │ → admin.certification-categories.index
├─────────────────────────────────┤
│ 承認                            │
│ 修了申請承認 [check-badge] (N)  │ → admin.enrollments.pending
├─────────────────────────────────┤
│ 分析                            │
│ 運用統計      [chart-bar]       │ → admin.stats.index
├─────────────────────────────────┤
│ 共通                            │
│ 通知          [bell] (N)        │ → notifications.index
│ 設定          [cog]             │ → settings.profile.edit
└─────────────────────────────────┘
```

`(N)` は通知 / 修了申請待ちの **未対応件数バッジ**（後述「サイドバー実装規約」）。

### coach（コーチ）

```
┌─────────────────────────────────┐
│ ダッシュボード         [home]    │ → dashboard.index
├─────────────────────────────────┤
│ 受講生                          │
│ 担当受講生  [user-group]        │ → coach.students.index
├─────────────────────────────────┤
│ コンテンツ                      │
│ 教材管理     [book-open]        │ → admin.certifications.index
│   ※ admin 共有 URL              │
│ 模試マスタ [clipboard-document-check] │ → admin.mock-exams.index
├─────────────────────────────────┤
│ 対応                            │
│ chat 対応 [chat-bubble-left-right] (N) │ → coach.chat.index
│ 質問対応 [question-mark-circle] (N)    │ → coach.qa-board.index
│ 面談管理 [calendar-days]   (N)         │ → coach.meetings.index
├─────────────────────────────────┤
│ 共通                            │
│ 通知          [bell] (N)        │ → notifications.index
│ 設定          [cog]             │ → settings.profile.edit
└─────────────────────────────────┘
```

### student（受講生）

```
┌─────────────────────────────────┐
│ ダッシュボード         [home]    │ → dashboard.index
├─────────────────────────────────┤
│ 学習                            │
│ 資格カタログ [magnifying-glass] │ → certifications.index
│ 教材          [book-open]       │ → contents.index
│ 模試 [clipboard-document-check] (N) │ → mock-exams.index
├─────────────────────────────────┤
│ 相談                            │
│ chat (コーチへ) [chat-bubble-left-right] (N) │ → chat.index
│ 質問掲示板 [question-mark-circle] │ → qa-board.index
│ AI 相談       [sparkles]        │ → ai-chat.index
│ 面談予約      [calendar-days]   │ → meetings.index
├─────────────────────────────────┤
│ 共通                            │
│ 通知          [bell] (N)        │ → notifications.index
│ 設定          [cog]             │ → settings.profile.edit
└─────────────────────────────────┘
```

### サイドバー実装規約

1. **`Route::has()` でガード**: 未実装 Feature のルートは表示しない。`<x-nav.item>` 内部で自動チェック（[frontend-blade.md](./frontend-blade.md) 参照）
2. **アクティブハイライト**: `request()->routeIs($route . '*')` でグループ単位の判定。`/admin/users/123/edit` でも「ユーザー管理」が active
3. **バッジ集約**: 未読 chat / 未読通知 / 未回答 Q&A / 今日の面談 / 修了申請待ち 等の集計は **`App\View\Composers\SidebarBadgeComposer`** で 1 リクエスト 1 回だけ集計（DB クエリ束ね）。サイドバー Blade が `$sidebarBadges` 配列を参照する形にする
4. **モバイル**: `lg:` 未満で drawer 化（off-canvas + バックドロップ）。`resources/js/components/sidebar-drawer.js` で開閉

### TopBar 構造

```
┌────────────────────────────────────────────────────────┐
│ [☰ (mobile)] [ロゴ]      [通知ベル▼] [ユーザーアバター▼] │
└────────────────────────────────────────────────────────┘
```

- 通知ベル: クリックで `/notifications` へ遷移。未読数バッジを表示（赤丸 + 数字、99+ で表示打ち切り）
- ユーザーアバター ドロップダウン: 「プロフィール」`settings.profile.edit` / 「ログアウト」`logout`
- モバイル: 左端にハンバーガー、`sidebar-drawer.js` を起動

## ロール共通画面の責務分担

複数 Feature にまたがる「ロール共通の画面」は **どの Feature が所有するか** を以下に集約。各 Feature spec 執筆時に重複定義が出ないようにする。

| 画面 | URL | 所有 Feature | 備考 |
|---|---|---|---|
| ログイン | `/login` | [[auth]] | Fortify 標準 |
| パスワードリセット要求 | `/forgot-password` | [[auth]] | Fortify 標準 |
| パスワードリセット確認 | `/reset-password/{token}` | [[auth]] | Fortify 標準 |
| オンボーディング | `/onboarding/{invitation}` | [[auth]] | 招待URL からの初回登録 |
| ダッシュボード | `/dashboard` | [[dashboard]] | ロール別 Blade に分岐（admin / coach / student） |
| プロフィール表示・編集 | `/settings/profile` | [[settings-profile]] | 自分のプロフィール |
| パスワード変更 | `/settings/password` | [[settings-profile]] | Fortify Password Update |
| 通知設定 | `/settings/notifications` | [[settings-profile]] | 通知種別 × channel ON/OFF |
| 自己退会 | `/settings/withdraw` | [[settings-profile]] | active → withdrawn 自己遷移 |
| 面談可能時間枠（coach のみ） | `/settings/availability` | [[settings-profile]] | CoachAvailability、[[mentoring]] と共有 |
| 通知一覧 | `/notifications` | [[notification]] | Database channel の通知を時系列表示 |
| エラーページ (403 / 404 / 419 / 500) | — | **本ドキュメント所有** | `resources/views/errors/` |

> Why この分担: 「自分自身のリソースを操作する画面」は [[settings-profile]] が所有、「admin が他者を操作する画面」は [[user-management]] が所有、というルールで「管理 vs 自己」を責務分離する（[product.md](../../docs/steering/product.md) Feature 一覧表の `settings-profile` 行参照）。

### エラーページ（本ドキュメント所有）

`resources/views/errors/` 配下に配置。`layouts/guest.blade.php` を継承（認証状態に関わらず表示可能）。

| ファイル | 用途 | 主要要素 |
|---|---|---|
| `errors/403.blade.php` | 認可エラー（Policy 拒否、`AccessDeniedHttpException`） | 「権限がありません」+ ダッシュボードへ戻るリンク |
| `errors/404.blade.php` | リソース未存在（Route Model Binding 失敗 / `NotFoundHttpException`） | 「お探しのページが見つかりません」+ ダッシュボードへ戻るリンク |
| `errors/419.blade.php` | CSRF トークン失効 | 「セッションが切れました。再度ログインしてください」+ ログインへ戻るリンク |
| `errors/422.blade.php` | バリデーション失敗で fallback 表示が必要なケース（通常は元フォームへ戻る） | 「入力内容に誤りがあります」 |
| `errors/500.blade.php` | 内部エラー（`HttpException(500)` / 未捕捉例外） | 「一時的なエラーが発生しました。時間をおいて再度お試しください」 |
| `errors/maintenance.blade.php` | メンテナンスモード（`php artisan down`） | 「メンテナンス中です」 |

各エラーページは:
- `layouts/guest.blade.php` を継承
- ロゴ + 大きなエラーコード（`<h1 class="text-6xl">404</h1>` 等）+ 説明文 + アクションボタン
- 認証済ユーザーがアクセスした場合のみ「ダッシュボードへ戻る」、未認証なら「ログインへ」へ動的に切替

## デザイントークン要件（Wave 0a への指示書）

Wave 0a の Claude Design Web UI で Design System を生成する際、以下の制約を満たすこと。Wave 0b で `tailwind.config.js` の `theme.extend` に反映される。

### カラーパレット要件

業界標準（Tailwind / shadcn/ui）の **セマンティックカラー命名** に揃える。各色は Tailwind 標準の 50 〜 950 スケールで提供（Wave 0a 出力時に全段階を提示）。

| トークン名 | 用途 | 推奨ベース |
|---|---|---|
| `primary` | ボタン / リンク / アクティブ / フォーカスリング | Tailwind `blue-600` 起点 |
| `secondary` | 補助ボタン / 境界線 / 無効化テキスト | Tailwind `gray-500` 起点 |
| `success` | 完了 / 成功 / 公開済バッジ | Tailwind `green-600` 起点 |
| `warning` | 注意 / 一時停止 / draft バッジ | Tailwind `yellow-600` 起点 |
| `danger` | 削除 / エラー / 失敗バッジ | Tailwind `red-600` 起点 |
| `info` | 補足情報 / 進行中バッジ | Tailwind `cyan-600` 起点（primary との色相分離） |

コントラスト比要件: **WCAG AA 準拠**（通常テキスト 4.5:1、大型テキスト 18px 以上または 14px bold で 3:1、UI コンポーネント 3:1）。

### タイポグラフィ要件

| トークン名 | 用途 | 推奨 |
|---|---|---|
| `font-sans` | 本文・UI | `Noto Sans JP` + `Inter`（日本語 / 英数字の混植が綺麗） |
| `font-mono` | コードブロック・serial_no | `JetBrains Mono` または `IBM Plex Mono` |
| `font-pdf` | PDF 専用日本語 | `IPAGothic`（dompdf 同梱、[[certification-management]] Certificate PDF 用） |

サイズスケールは Tailwind 標準（`text-xs` 〜 `text-6xl`）を採用。行間は `leading-normal` / `leading-relaxed` を本文標準。

### スペーシング / 角丸 / 影

Tailwind 標準のスペーシングスケール（0 / 1 / 2 / 3 / 4 / 5 / 6 / 8 / 10 / 12 / 16 / 20 / 24 / 32 / 40 / 48 / 64）を採用。カスタムは原則追加しない。

| トークン | 値 | 用途 |
|---|---|---|
| `rounded` | 4px | バッジ / インライン要素 |
| `rounded-md` | 6px | ボタン / インプット |
| `rounded-lg` | 8px | カード / モーダル |
| `rounded-xl` | 12px | 大きめカード / ダイアログ |
| `rounded-full` | 9999px | アバター / ピル |
| `shadow-sm` | subtle | カード（軽）|
| `shadow` | default | カード（標準）|
| `shadow-md` | medium | ドロップダウン / トースト |
| `shadow-lg` | strong | モーダル |
| `shadow-xl` | extra | 重要なモーダル / 通知 |

## アクセシビリティ要件（WCAG 2.1 AA 相当）

各 Feature 実装時に守る：

1. **キーボード操作**: 全 interactive 要素にフォーカス可（`<button>` / `<a>` / 自前要素は `tabindex`）。フォーカスリングを必ず表示（`focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2`）。`Tab` 順序が視覚順序と一致
2. **スクリーンリーダー**: アイコンのみのボタンには `aria-label`、装飾アイコンには `aria-hidden="true"`、フォームには `<label for>` 必須
3. **コントラスト**: テキスト 4.5:1、UI コンポーネント / 大型テキスト 3:1（Wave 0a の Design System 制約として要求）
4. **モーダル**: フォーカストラップ、`Esc` で閉じる、`aria-modal="true"` + `role="dialog"` + `aria-labelledby="{id}-title"`
5. **テーブル**: `<th scope="col">` 必須、行操作には `aria-label`（例: `aria-label="ユーザー 山田太郎 を編集"`）
6. **フォームエラー**: フィールドに `aria-describedby="{name}-error"` で紐付け、エラー文に `role="alert"`
7. **動的更新**: AJAX 後の動的コンテンツ追加は `aria-live="polite"` の領域で通知
8. **言語**: `<html lang="ja">` 必須

## レスポンシブブレークポイント

Tailwind 標準を採用：

| Prefix | min-width | 主な使い分け |
|---|---|---|
| デフォルト | 0px | モバイル（縦長） |
| `sm:` | 640px | モバイル横置き / 小タブレット |
| `md:` | 768px | タブレット |
| `lg:` | 1024px | **サイドバー固定表示の起点** |
| `xl:` | 1280px | 広めのデスクトップ |
| `2xl:` | 1536px | 超広 |

- `lg:` 未満ではサイドバーが drawer 化
- 主要な業務画面（管理一覧 / mock-exam 受験 / コーチダッシュボード等）は `lg:` 以上を想定。`md:` 以下は最低限の閲覧確認のみ
- mock-exam 受験画面のみモバイルでも受験可能とする（受験生が外出先で受ける可能性を考慮）

## JavaScript 統合点

共通コンポーネントの JS は `resources/js/components/` 配下。詳細規約は [frontend-javascript.md](./frontend-javascript.md) 参照。

| ファイル | 役割 | 関連コンポーネント |
|---|---|---|
| `modal.js` | モーダル開閉 / フォーカストラップ | `<x-modal>` |
| `dropdown.js` | ドロップダウン開閉 / 外側クリック検知 | `<x-dropdown>` |
| `tabs.js` | タブ切替（クエリストリング操作） | `<x-tabs>` |
| `flash.js` | dismissible Alert の閉じる動作 | `<x-alert :dismissible>` |
| `sidebar-drawer.js` | モバイル時の drawer 開閉 | `layouts/_partials/sidebar-*.blade.php` |
| `textarea-counter.js` | textarea の文字数カウンタ | `<x-form.textarea maxlength=...>` |

`resources/js/app.js` から各 `components/*.js` を `import`、`DOMContentLoaded` でセレクタを走査して初期化。

## Wave 0a への指示書サマリ

Claude Design Web UI へ以下を依頼する：

### Design System として生成

1. **カラーパレット**: 上記「カラーパレット要件」準拠（primary / secondary / success / warning / danger / info の 6 系統、各 50-950 スケール）
2. **タイポグラフィ**: 日本語 + 英数字 + monospace（上記「タイポグラフィ要件」準拠）
3. **コンポーネント** ([frontend-blade.md](./frontend-blade.md) の「共通コンポーネント API」と必ず整合させる):
   - **Buttons**: Button（5 variant × 3 size）/ LinkButton
   - **Forms**: Input / Textarea / Select / Checkbox / Radio / File / Error / Label / Hint / Fieldset
   - **Display**: Card / Badge（5 variant） / Avatar / Icon / EmptyState
   - **Overlay**: Modal / Dropdown
   - **Feedback**: Alert（4 variant） / Flash
   - **Navigation**: NavSidebar / NavItem / NavSection / Paginator / Breadcrumb / Tabs
   - **Tables**: Table / Row / Heading / Cell

### Hero Screens として生成（6 枚）

[product.md](../../docs/steering/product.md) の各ロール動線から **特に視覚的密度が高い 6 画面** を選定（[CLAUDE.md](../../CLAUDE.md) Wave 0a セクション準拠）:

1. **受講生ダッシュボード** — 試験日カウントダウン / 進捗ゲージ / 学習ストリーク / 弱点パネル / 目標タイムライン（Wantedly 風）/ 通知
2. **mock-exam 受験画面** — タイマー（残り時間カウントダウン）/ 問題本文 / 選択肢ラジオ / 進捗ピル（N/80）/ 提出ボタン
3. **mock-exam 結果（弱点ヒートマップ）** — 分野別正答率 / 合格可能性スコア（3 バンド表示）/ 苦手ドリルへの導線
4. **qa-board 一覧** — スレッドリスト / 資格別フィルタ / 未解決フィルタ / 検索 / 投稿ボタン
5. **コーチダッシュボード** — 担当受講生進捗一覧 / 未対応 chat / 未回答 Q&A / 今日の面談 / 滞留検知リスト
6. **管理者ダッシュボード** — 全体 KPI / 修了申請待ち一覧 / 滞留検知 / 統計サマリ

### サイドバー（ロール別 3 種）

本ドキュメント「サイドバー構造（ロール別）」セクション準拠。各メニュー項目に Heroicons (outline) を当てる。Hero Screens の各画面に組み込んだ状態で提示。

## ポスト Wave 0a の調整フロー

Wave 0a Claude Design のハンドオフ完了後、Wave 0b で **以下の3点のみ** 反映すれば全 Feature の Blade が新デザインで動く。Spec / `frontend-blade.md` の記述は **書き換え不要**（セマンティックトークンで書かれているため）:

| 反映先 | Wave 0a 出力から取得する値 | 例 |
|---|---|---|
| `tailwind.config.js` の `theme.extend.colors` | primary / secondary / success / warning / danger / info の各 50〜950 スケール | `colors.primary = { 50: '#EFF6FF', 100: '#DBEAFE', ..., 950: '#172554' }` |
| `tailwind.config.js` の `theme.extend.fontFamily` | sans / mono / pdf のフォントスタック | `fontFamily.sans = ['Noto Sans JP', 'Inter', 'sans-serif']` |
| `resources/css/app.css` | 必要時のみ `@layer base` / カスタム CSS 変数 / フォント `@import` | `@import url('https://fonts.googleapis.com/...')` |

→ `bg-primary-600` / `bg-danger-50` / `text-success-800` 等の Blade 記述は **そのまま新色で描画される**。Spec も `frontend-blade.md` も触らない。

### 個別画面の調整

各 Feature の個別画面（user 一覧 / 教材編集 等）は Wave 0a で起こさない（Hero Screens 6枚に限定）。**Design System + 共通コンポーネント + spec のレイアウト指示** から組み立てる:

1. spec の `## Blade ビュー` セクションが画面構造を指示
2. `frontend-blade.md` の共通コンポーネント API（`<x-button>` 等）が部品を提供
3. Wave 0b で実装した Design System が視覚を提供
4. 必要に応じて `frontend-design` プラグインで Blade を生成（AI スロップ回避）

## Wave 0b の完成判定基準（Definition of Done）

Wave 0b 実装完了の判定チェックリスト。本セクションが満たされるまで Feature 実装フェーズに進まない。

### 依存パッケージ

- [ ] `composer.json` に下記が追加され `sail composer install` 成功
  - `laravel/fortify`（認証）
  - `laravel/sanctum`（API トークン）
  - `league/commonmark`（Markdown）
  - `barryvdh/laravel-dompdf`（PDF）
  - `blade-ui-kit/blade-heroicons`（Heroicons）
- [ ] `package.json` に下記が追加され `sail npm install` 成功
  - `tailwindcss` + `@tailwindcss/forms` + `@tailwindcss/typography`
  - `vite` + `laravel-vite-plugin`
  - `postcss` + `autoprefixer`

### Tailwind / Vite 設定

- [ ] `tailwind.config.js` に Wave 0a 出力の `theme.extend.colors` / `fontFamily` / `boxShadow` / `borderRadius` を反映
- [ ] `tailwind.config.js` の `content` に `./resources/views/**/*.blade.php` と `./resources/js/**/*.js` が含まれる
- [ ] `vite.config.js` で `resources/css/app.css` + `resources/js/app.js` がエントリ
- [ ] `resources/css/app.css` に `@tailwind base; @tailwind components; @tailwind utilities;` + Wave 0a の追加 CSS（必要時）
- [ ] `sail npm run build` 成功 + `sail npm run dev` で HMR 動作

### 認証基盤

- [ ] `app/Models/User.php` 実装（`HasUlids` + `SoftDeletes` + `Notifiable`、[[auth]] の Model 規約準拠）
- [ ] `database/migrations/{date}_create_users_table.php` 実装（`name` / `password` nullable、`role` / `status` enum）
- [ ] `app/Enums/UserRole.php` + `UserStatus.php` 実装（`label()` 含む）
- [ ] Sanctum の `personal_access_tokens` migration 公開済
- [ ] Fortify の `FortifyServiceProvider` 雛形作成（実カスタマイズは [[auth]] 実装時）

### 共通モデル

- [ ] `app/Models/UserStatusLog.php` 実装（[[user-management]] の Model 規約準拠）
- [ ] `database/migrations/{date}_create_user_status_logs_table.php` 実装

### 共通レイアウト

- [ ] `resources/views/layouts/app.blade.php` 実装（TopBar + Sidebar + Main + Flash）
- [ ] `resources/views/layouts/guest.blade.php` 実装（中央カードレイアウト）
- [ ] `resources/views/layouts/pdf.blade.php` 実装（dompdf 向け、外部 CSS / JS なし）
- [ ] `resources/views/layouts/_partials/sidebar-admin.blade.php` 実装
- [ ] `resources/views/layouts/_partials/sidebar-coach.blade.php` 実装
- [ ] `resources/views/layouts/_partials/sidebar-student.blade.php` 実装
- [ ] `resources/views/layouts/_partials/topbar.blade.php` 実装
- [ ] `app/View/Composers/SidebarBadgeComposer.php` 実装（各バッジ集計の集約、現時点で利用可能なクエリのみ実装、Feature 追加に合わせて拡張）

### 共通 Blade コンポーネント

[frontend-blade.md](./frontend-blade.md) の「共通コンポーネント API」全項目が `resources/views/components/` に実装され、視覚的に表示可能：

- [ ] `button.blade.php` / `link-button.blade.php`
- [ ] `form/input.blade.php` / `textarea.blade.php` / `select.blade.php` / `checkbox.blade.php` / `radio.blade.php` / `file.blade.php` / `error.blade.php` / `label.blade.php` / `hint.blade.php` / `fieldset.blade.php`
- [ ] `card.blade.php` / `badge.blade.php` / `avatar.blade.php` / `icon.blade.php` / `empty-state.blade.php`
- [ ] `modal.blade.php` / `dropdown.blade.php` / `dropdown/item.blade.php`
- [ ] `alert.blade.php` / `flash.blade.php`
- [ ] `nav/sidebar.blade.php` / `nav/item.blade.php` / `nav/section.blade.php` / `paginator.blade.php` / `breadcrumb.blade.php` / `tabs.blade.php`
- [ ] `table.blade.php` / `table/row.blade.php` / `table/heading.blade.php` / `table/cell.blade.php`

### 共通 JavaScript

- [ ] `resources/js/app.js` から `components/*.js` を import
- [ ] `resources/js/components/modal.js` 実装（開閉 / フォーカストラップ / Esc）
- [ ] `resources/js/components/dropdown.js` 実装
- [ ] `resources/js/components/tabs.js` 実装
- [ ] `resources/js/components/flash.js` 実装
- [ ] `resources/js/components/sidebar-drawer.js` 実装
- [ ] `resources/js/components/textarea-counter.js` 実装
- [ ] `resources/js/utils/fetch-json.js` 実装（CSRF + JSON 共通ラッパー、[frontend-javascript.md](./frontend-javascript.md) 参照）

### エラーページ

- [ ] `resources/views/errors/403.blade.php` 実装
- [ ] `resources/views/errors/404.blade.php` 実装
- [ ] `resources/views/errors/419.blade.php` 実装
- [ ] `resources/views/errors/422.blade.php` 実装
- [ ] `resources/views/errors/500.blade.php` 実装
- [ ] `resources/views/errors/maintenance.blade.php` 実装

### ルート骨組み

- [ ] `routes/web.php` に `auth` middleware group の雛形 + dashboard / settings / notifications のプレースホルダルート
- [ ] `routes/api.php` に Sanctum 認証 group の雛形

### 動作確認（手動）

- [ ] `sail up -d` 後に `http://localhost` でログイン画面が表示される
- [ ] 各ロールでログイン後、サイドバー / TopBar / ダッシュボードプレースホルダが表示される
- [ ] モバイルサイズ（`lg:` 未満）でサイドバーが drawer 化する
- [ ] 各共通コンポーネントの単純表示テスト用ページ（例: `/_dev/components`、本番では `APP_ENV=local` のみ表示）で全コンポーネントの variant / size を視認確認

> Wave 0b 完了後、Feature 実装フェーズに進む。Feature 実装中は本セクションのチェックリスト項目を **編集禁止**（[CLAUDE.md](../../CLAUDE.md)「Wave 0b で確定する基盤資産」セクション参照）。
