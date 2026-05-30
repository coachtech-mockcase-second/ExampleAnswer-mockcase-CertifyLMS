# ONBOARDING — フロントエンド構造ガイド

このプロジェクトの **フロントエンド（Blade / CSS / JavaScript）は完成形で提供されています**。あなたが実装するのは主に **バックエンド**（コントローラ・ロジック・認可・バリデーション等）です。本ガイドは「どこに何があるか」の地図で、提供 UI を素早く読み解き、バックエンド実装に集中できるようにするためのものです。

> 各 Blade / JS ファイルの先頭には、その画面・部品の **役割と構成** を説明するヘッダコメントが付いています。コメントは **フロントの構造と挙動だけ** を説明し、バックエンド設計には触れません（必要なバックエンドは、画面を読んで自分で設計します）。

---

## 1. ディレクトリ全体像

```
resources/
├── views/
│   ├── layouts/            # 共通レイアウト（app / guest / pdf）+ _partials（サイドバー・TopBar）
│   ├── components/          # 共通 Blade コンポーネント（<x-...>）
│   ├── errors/              # エラーページ（403/404/419/422/500/maintenance）
│   ├── {feature}/           # 機能ごとの画面（後述の「画面の構成パターン」）
│   └── ...
├── css/
│   └── app.css              # Tailwind エントリ + デザイントークン（CSS 変数）
└── js/
    ├── app.js               # JS エントリ（各モジュールの初期化を集約）
    ├── components/          # 共通 UI 挙動（modal / dropdown 等）
    ├── utils/               # 共通ユーティリティ（fetch ラッパー等）
    └── {feature}/           # 機能ごとの JS
```

ビルドは Vite（`npm run dev` / `npm run build`）。Blade からは `@vite(['resources/css/app.css', 'resources/js/app.js'])` で読み込みます。

---

## 2. レイアウト（`resources/views/layouts/`）

| ファイル | 用途 |
|---|---|
| `app.blade.php` | **認証後の共通レイアウト**。全ロール（受講生 / コーチ / 管理者）の画面が継承。サイドバー（`lg+` 固定 / 未満は drawer）+ TopBar + メイン（`@yield('content')`）。 |
| `guest.blade.php` | **未ログイン用**。中央のフォームカード。ログイン / オンボーディング / パスワードリセット / エラーページが継承。 |
| `pdf.blade.php` | PDF 生成専用（外部 CSS/JS なし）。 |
| `_partials/sidebar-{role}.blade.php` | ロール別サイドバー（`@include('layouts._partials.sidebar-' . role)` で切替）。 |
| `_partials/topbar.blade.php` | 全画面共通ヘッダ（検索 / 通知ベル / ユーザーメニュー）。 |

画面を作るときは `@extends('layouts.app')`（認証後）または `@extends('layouts.guest')`（認証前）で継承します。

---

## 3. 共通コンポーネント（`resources/views/components/` = `<x-...>`）

**まずここを見てください。** 同じ UI を再発明せず、既存コンポーネントを組み合わせて画面を作れます。各ファイル先頭ヘッダに props / slot の概要があります。

| カテゴリ | コンポーネント | 用途の例 |
|---|---|---|
| ボタン | `<x-button>` / `<x-link-button>` | variant × size のボタン / リンク見た目のボタン |
| フォーム | `<x-form.input>` `textarea` `select` `checkbox` `radio` `file` `label` `hint` `error` `fieldset` | ラベル + 入力 + エラー表示が揃ったフォーム部品 |
| 表示 | `<x-card>` `<x-badge>` `<x-avatar>` `<x-icon>` `<x-empty-state>` | カード / ステータスバッジ / アバター / アイコン / 空状態 |
| オーバーレイ | `<x-modal>` `<x-dropdown>`（+ `dropdown.item`） | モーダル / ドロップダウン（素の JS で開閉） |
| フィードバック | `<x-alert>` `<x-flash>` | アラート / フラッシュメッセージ |
| ナビ | `<x-nav.sidebar>` `nav.item` `nav.section` `<x-breadcrumb>` `<x-tabs>` `<x-paginator>` | サイドバー / パンくず / タブ / ページネーション |
| テーブル | `<x-table>`（+ `table.row` `table.heading` `table.cell`） | 一覧テーブル |
| その他 | `<x-enrollment-switcher>` / `components/content-management/*` / `components/ai-chat/*` | 受講資格スイッチャー / 教材公開状態バッジ等 |

ローカルの `APP_ENV=local` でアクセスできる **`/_dev/components`** で、全コンポーネントの見た目を一覧確認できます。

---

## 4. 画面の構成パターン（`resources/views/{feature}/`）

機能ごとにディレクトリがあり、慣習的に以下の構成です。

- `index.blade.php` — 一覧、`show.blade.php` — 詳細、`create` / `edit` — フォーム
- `_partials/` — 画面内で再利用する部品（カード・行・タイムライン等）
- `_modals/` — モーダル（確認ダイアログ・フォーム）

各ファイル先頭ヘッダの「構成:」行を読むと、その画面がどんなブロックで組まれているか一目で分かります。

---

## 5. JavaScript（`resources/js/`）

**フレームワークは使いません（素の JavaScript + Vite）。** `app.js` が各モジュールの `init...()` を `DOMContentLoaded` で呼ぶ構成です。

| 区分 | 場所 | 例 |
|---|---|---|
| エントリ | `app.js` | 各モジュールの初期化を集約 |
| 共通 UI 挙動 | `components/` | `modal` / `dropdown` / `flash` / `sidebar-drawer` / `textarea-counter` / `enrollment-switcher` |
| 共通ユーティリティ | `utils/` | `fetch-json`（fetch + CSRF + JSON のラッパー） |
| 機能別 | `{feature}/` | `ai-chat/*` / `mock-exam/*` / `mentoring/*` / `content-management/*` / `dashboard/*` / `notification/*` 等 |

### Blade ↔ JS の連携（`data-*` フック）

JS は **`data-*` 属性** を目印に DOM を探して動作します（Blade 側が `data-modal-trigger="..."` 等を出し、JS がそれを拾う）。各 JS モジュール先頭ヘッダに「どの `data-*` を見るか」「公開する `init` 関数」が書かれています。**Blade と JS の接点は `data-*` 属性** と覚えておくと読みやすくなります。

> JS の API 呼び出し先（エンドポイント）や送受信データの詳細は、あなたが設計するバックボーンに関わるため、コメントでは深掘りしていません。提供 JS が「どの `data-*` を読み、どんな操作をするか」（フロント挙動）に注目してください。

---

## 6. スタイリング（Tailwind CSS）

- `resources/css/app.css` にデザイントークン（CSS 変数: `--border-subtle` 等）、`tailwind.config.js` にカラー（`primary` / `ink` / `surface` / `border-subtle` 等）・フォント・影を定義。
- ユーティリティファースト。`bg-primary-600` / `text-ink-900` / `border-subtle` のような **意味のある名前のクラス** を使います。
- 同じクラス組み合わせが 3 回以上出たらコンポーネント化（`<x-...>`）されています。

---

## 7. 読み進め方の目安

1. まず **`layouts/app.blade.php`** と **サイドバー / TopBar** で全体の枠を掴む。
2. **`components/`**（または `/_dev/components`）で使える部品を把握する。
3. 担当する機能の `resources/views/{feature}/` の各ファイル先頭ヘッダを読み、画面の構成を理解する。
4. 動的 UI があれば、対応する `resources/js/{feature}/` のヘッダで「どの `data-*` を見て何をするか」を確認する。
5. 画面が「どんな入力を受け、何を表示するか」を読み取り、それを満たす **バックエンドを自分で設計** する。
