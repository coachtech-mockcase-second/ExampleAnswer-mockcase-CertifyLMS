---
paths:
  - "提供プロジェクト/resources/views/**"
  - "模範解答プロジェクト/resources/views/**"
---

# Blade テンプレート規約

> Blade を書く時の文法規約 + 共通レイアウト + 共通コンポーネント API。
> アプリケーション全体の UI 構造（サイドバー / Wave 0a/0b 完成判定 / デザイントークン）は [frontend-ui-foundation.md](./frontend-ui-foundation.md) を参照。

## このドキュメントの役割

**Micro 規約**: Blade テンプレートを書くときの **手元のルール**（文法 / 共通コンポーネント API 契約 / 命名）。

**Macro 規約**（アプリ全体の UI 構造、ロール別サイドバー、デザイントークン要件、Wave 0a/0b 完成判定）は [frontend-ui-foundation.md](./frontend-ui-foundation.md) を参照。

両者の使い分け:

- 個別 Blade ファイルを書くとき → **本ドキュメント**
- レイアウト構造 / ロール別シェル / Wave 0a/0b 進行 → **frontend-ui-foundation.md**

### デザインのカスタマイズ可能性（重要）

本ドキュメントのスタイル例は **セマンティックトークン** で記述する（業界標準 shadcn/ui 流）:

| カテゴリ | セマンティックトークン | tailwind.config.js での実装 |
|---|---|---|
| ブランド | `bg-primary-600`, `text-primary-600`, `ring-primary-500` | `theme.extend.colors.primary.{50..950}` |
| 補助 | `bg-secondary-500`, `text-secondary-500` | `theme.extend.colors.secondary.{50..950}` |
| 状態 | `bg-success-{50..600}` / `bg-danger-{50..600}` / `bg-warning-{50..600}` / `bg-info-{50..600}` | `theme.extend.colors.{success,danger,warning,info}.{50..950}` |
| 中立 | `bg-gray-{50..900}` / `text-gray-{500..900}` | Tailwind 標準 `gray` をそのまま使用（Wave 0a で再定義しない）|

**カスタマイズフロー**: Wave 0a Claude Design が `primary` 等の実値（例: `#3B82F6` = blue / `#7C3AED` = violet）を出力 → Wave 0b で `tailwind.config.js` の `theme.extend.colors.primary` に設定 → 本ドキュメントの記述（`bg-primary-600` 等）は **変更不要**。

カラー / フォント / 角丸 / 影 等の **デザイントークン要件** は [frontend-ui-foundation.md](./frontend-ui-foundation.md#デザイントークン要件wave-0a-への指示書) を参照。

## ディレクトリ構成

```
resources/views/
├── layouts/                # 共通レイアウト
│   ├── app.blade.php       #   ログイン後の主レイアウト（サイドバー + ヘッダー + メイン）
│   ├── guest.blade.php     #   未ログイン（ロゴ + 中央カード）
│   ├── pdf.blade.php       #   dompdf 用（共通レイアウト非継承、インライン <style>）
│   └── _partials/          #   レイアウト内部品（ロール別サイドバー等）
│       ├── sidebar-admin.blade.php
│       ├── sidebar-coach.blade.php
│       ├── sidebar-student.blade.php
│       └── topbar.blade.php
├── components/             # Blade コンポーネント（再利用 UI、本ドキュメント「共通コンポーネント API」参照）
│   ├── button.blade.php
│   ├── form/
│   ├── modal.blade.php
│   ├── nav/
│   └── ...
├── auth/                   # 認証系（Fortify、[[auth]] 所有）
├── errors/                 # エラーページ（[frontend-ui-foundation.md] 所有）
├── dashboard/              # ダッシュボード（[[dashboard]] 所有、ロール別）
│   ├── admin.blade.php
│   ├── coach.blade.php
│   └── student.blade.php
└── {feature}/              # Feature 単位（enrollment / mock-exam / chat 等）
    ├── index.blade.php
    ├── show.blade.php
    └── form.blade.php
```

## 必須事項

- `@csrf` トークンはすべてのフォームに必須
- `@method('PUT')` / `@method('DELETE')` で動詞偽装
- 認可表示は `@can` / `@cannot` で制御
- ナビゲーション表示は `@if(Route::has('xxx'))` で防衛（未実装ルートでのエラー防止）
- Blade コンポーネントは `x-` プレフィクス（`<x-button>`）
- 動的データは `{{ $var }}`（自動エスケープ）、HTML 埋め込みは `{!! !!}`（XSS 注意、信頼できる Service の出力のみ）
- 親レイアウトは原則 `@extends('layouts.app')`（認証後）or `@extends('layouts.guest')`（認証前）

## 共通レイアウトの contract

レイアウトの **構造設計**（サイドバー / TopBar / ロール別表示 / デザイン詳細）は [frontend-ui-foundation.md#アプリケーションシェル](./frontend-ui-foundation.md#アプリケーションシェル) を参照。本セクションは **継承する側（Feature ビュー）から見た契約** のみ記述。

### `layouts/app.blade.php`（認証後の主レイアウト）が提供する slot / yield

| 名前 | 用途 |
|---|---|
| `@yield('title')` | `<title>` 内に挿入。Feature 側で `@section('title', 'ユーザー一覧')` |
| `@yield('content')` | 主コンテンツ領域（サイドバー + TopBar の右側）|
| `@stack('scripts')` | 追加 `<script>` を `app.js` 後に積む（Feature 個別 JS の読み込み）|

最小利用例:

```blade
@extends('layouts.app')

@section('title', 'ユーザー一覧')

@section('content')
    <h1 class="text-2xl font-bold">ユーザー一覧</h1>
    {{-- ... --}}
@endsection

@push('scripts')
    @vite('resources/js/user-management/index.js')
@endpush
```

### `layouts/guest.blade.php`（未ログイン）

- ロゴ中央 + フォームカード中央表示、サイドバー無し
- ログイン / オンボーディング / パスワードリセット / エラーページが継承
- slot / yield は `@yield('title')` / `@yield('content')` のみ

### `layouts/pdf.blade.php`（dompdf 専用）

- `app.blade.php` を **継承しない**（dompdf は外部 CSS / JS を解釈しないため、インライン `<style>` のみ）
- 日本語フォント `IPAGothic` を `font-family` で指定
- 修了証 PDF（[[certification-management]]）が利用

## 共通コンポーネント API（契約定義）

各 Feature spec の Blade セクションは本セクションに定義された API のみを参照する。新規コンポーネントが必要になった場合は、本ドキュメントを先に更新してから Blade 実装に進む（spec → 規約 → 実装 の順序を保つ）。

### 業界標準準拠

- **命名**: shadcn/ui の Prop 命名規則（variant / size / type / disabled）に揃える
- **アイコン**: [Heroicons v2](https://heroicons.com)（outline / solid、kebab-case 名）
- **アクセシビリティ**: WCAG 2.1 AA 相当（aria-* / role / focus-visible / コントラスト比）
- **レスポンシブ**: Mobile-first、`sm:` / `md:` / `lg:` プレフィクス

### Buttons

#### `<x-button>`

```blade
<x-button variant="primary" size="md" type="submit">送信</x-button>
```

```php
@props([
    'variant' => 'primary',  // primary | outline | ghost | danger | secondary
    'size' => 'md',          // sm | md | lg
    'type' => 'button',      // button | submit | reset
    'disabled' => false,
    'loading' => false,
])
```

スタイル（セマンティックトークン使用、`tailwind.config.js` の `theme.extend.colors` で実値定義）:
- primary: `bg-primary-600 text-white hover:bg-primary-700`
- outline: `bg-transparent border border-gray-300 text-gray-800 hover:bg-gray-50`
- ghost: `bg-transparent text-gray-700 hover:bg-gray-100`
- danger: `bg-danger-600 text-white hover:bg-danger-700`
- secondary: `bg-gray-100 text-gray-800 hover:bg-gray-200`

サイズ:
- sm: `px-3 py-1.5 text-sm`
- md: `px-4 py-2 text-sm`
- lg: `px-6 py-3 text-base`

アクセシビリティ:
- `disabled` 時に `aria-disabled="true"` + `cursor-not-allowed`
- `loading` 時に `aria-busy="true"` + 内部スピナー表示 + 自動 disabled

外部属性は `$attributes->merge(['class' => '...'])` で継承（class の追加 / id / data-* 等を呼び出し側から渡せる）。

#### `<x-link-button>`

`<a>` レンダリング版。同じ variant / size を受け取る。

```blade
<x-link-button href="{{ route('admin.users.show', $user) }}" variant="outline">詳細</x-link-button>
```

### Forms

すべて Label + Input + Hint + Error の縦積みパターン。`name` 必須、`id` は `name` から自動生成。

#### `<x-form.input>`

```blade
<x-form.input
    name="email"
    label="メールアドレス"
    type="email"
    :value="old('email')"
    :error="$errors->first('email')"
    placeholder="user@example.com"
    hint="ログインに使用します"
    :required="true"
    autocomplete="email"
/>
```

```php
@props([
    'name',                                  // required
    'label' => null,
    'type' => 'text',                        // text|email|password|tel|date|datetime-local|number|url|search
    'value' => null,
    'error' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
])
```

- フォーカス時に `focus-visible:ring-2 focus-visible:ring-primary-500` を当てる
- `error` あり時に `aria-invalid="true"` + `aria-describedby="{name}-error"`
- `hint` を `<p id="{name}-hint" class="text-gray-500 text-sm">` で出力

#### `<x-form.textarea>`

```blade
<x-form.textarea
    name="description"
    label="説明"
    :rows="4"
    :value="old('description')"
    :error="$errors->first('description')"
    :maxlength="1000"
/>
```

`maxlength` 指定時はリアルタイム文字数カウンタを右下に表示（素の JS、`resources/js/components/textarea-counter.js`）。

#### `<x-form.select>`

```blade
<x-form.select
    name="role"
    label="ロール"
    :options="['coach' => 'コーチ', 'student' => '受講生']"
    :value="old('role')"
    :error="$errors->first('role')"
    placeholder="選択してください"
    :required="true"
/>
```

`options` は連想配列 `[$value => $label]`。

#### `<x-form.checkbox>` / `<x-form.radio>`

```blade
<x-form.checkbox name="agree" label="利用規約に同意する" :checked="false" />

{{-- ラジオは複数並べる --}}
<x-form.radio name="difficulty" value="easy" label="易" :checked="$question->difficulty === 'easy'" />
<x-form.radio name="difficulty" value="medium" label="中" :checked="$question->difficulty === 'medium'" />
<x-form.radio name="difficulty" value="hard" label="難" :checked="$question->difficulty === 'hard'" />
```

#### `<x-form.file>`

```blade
<x-form.file
    name="avatar"
    label="プロフィール画像"
    accept="image/png,image/jpeg,image/webp"
    :error="$errors->first('avatar')"
    hint="PNG / JPG / WebP、最大 2MB"
/>
```

#### `<x-form.error>` / `<x-form.label>` / `<x-form.hint>` / `<x-form.fieldset>`

入力フィールド単体使用時の補助。`<x-form.input>` の内部でも使用される。

```blade
<x-form.label for="custom" :required="true">名前</x-form.label>
<input id="custom" name="custom" />
<x-form.error name="custom" />
<x-form.hint>50 文字以内</x-form.hint>

<x-form.fieldset legend="個人目標">
    <x-form.input ... />
    <x-form.input ... />
</x-form.fieldset>
```

### Display

#### `<x-card>`

```blade
<x-card padding="md" shadow="sm">
    <x-slot:header>受講中資格</x-slot:header>
    {{ $slot }}
    <x-slot:footer>
        <x-link-button href="...">詳細</x-link-button>
    </x-slot:footer>
</x-card>
```

```php
@props([
    'padding' => 'md',   // none | sm | md | lg
    'shadow' => 'sm',    // none | sm | md | lg
])
```

`header` / `footer` named slot はオプショナル。

#### `<x-badge>`

```blade
<x-badge variant="success" size="sm">公開中</x-badge>
```

```php
@props([
    'variant' => 'gray',   // success | warning | danger | info | gray
    'size' => 'md',        // sm | md
])
```

ステータス表示で多用（draft / published / passed / failed / learning / ... の Enum `label()` を入れる用途）。

#### `<x-avatar>`

```blade
<x-avatar :src="$user->avatar_url" :name="$user->name" size="md" />
```

- `src` あり: `<img>` で表示
- `src` なし: `<span>` でイニシャル表示（背景色は name の hash から生成、業界慣習）
- size: `sm`=24px / `md`=40px / `lg`=64px / `xl`=96px

#### `<x-icon>`

```blade
<x-icon name="check-circle" variant="outline" class="w-5 h-5 text-success-600" />
```

```php
@props([
    'name',                  // required, kebab-case Heroicons v2 名
    'variant' => 'outline',  // outline | solid | mini
])
```

実装: `blade-ui-kit/blade-heroicons` パッケージ（Wave 0b で `composer require` 済）を内部で使用、または SVG を直接埋め込み。装飾的アイコンは `aria-hidden="true"` を自動付与、意味的アイコンは `aria-label` を呼び出し側で渡す。

#### `<x-empty-state>`

```blade
<x-empty-state
    icon="document-magnifying-glass"
    title="該当する教材がありません"
    description="検索キーワードを変えてもう一度お試しください"
>
    <x-slot:action>
        <x-link-button href="{{ route('contents.index') }}">教材一覧へ戻る</x-link-button>
    </x-slot:action>
</x-empty-state>
```

検索結果 0 件 / リスト未作成 / 権限なし時に使う。

### Overlay

#### `<x-modal>`

```blade
<x-modal id="invite-user-modal" title="ユーザーを招待" size="md">
    <x-slot:trigger>
        <x-button>+ 招待</x-button>
    </x-slot:trigger>
    <x-slot:body>
        <form method="POST" action="{{ route('admin.invitations.store') }}">
            @csrf
            <x-form.input name="email" label="メールアドレス" type="email" required />
            <x-form.select name="role" label="ロール" :options="$roleOptions" required />
        </form>
    </x-slot:body>
    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="invite-user-modal">キャンセル</x-button>
        <x-button type="submit" form="invite-user-form">送信</x-button>
    </x-slot:footer>
</x-modal>
```

```php
@props([
    'id',                // required
    'title' => null,
    'size' => 'md',      // sm | md | lg | xl
])
```

実装規約（`resources/js/components/modal.js`）:
- `data-modal-trigger="{id}"` で開く / `data-modal-close="{id}"` で閉じる
- バックドロップクリック / `Esc` で閉じる
- 開閉時に `aria-hidden` / `inert` 属性切替
- フォーカストラップ（モーダル内に Tab を閉じ込め）
- `role="dialog"` + `aria-modal="true"`

#### `<x-dropdown>`

```blade
<x-dropdown align="right">
    <x-slot:trigger>
        <x-button variant="ghost">操作 <x-icon name="chevron-down" class="w-4 h-4 ml-1" /></x-button>
    </x-slot:trigger>
    <x-dropdown.item href="{{ route('admin.users.edit', $user) }}">編集</x-dropdown.item>
    <x-dropdown.item href="{{ route('admin.users.destroy', $user) }}" method="delete">削除</x-dropdown.item>
</x-dropdown>
```

```php
@props([
    'align' => 'right',  // left | right
])
```

実装規約:
- 素の JS（`resources/js/components/dropdown.js`）で開閉
- 外側クリック / `Esc` で閉じる
- `aria-haspopup="menu"` / `aria-expanded` 切替
- `<x-dropdown.item method="delete">` は内部で hidden form + CSRF を出力

### Feedback

#### `<x-alert>`

```blade
<x-alert type="success" :dismissible="true">
    <x-slot:title>処理が完了しました</x-slot:title>
    ユーザーを招待しました。
</x-alert>
```

```php
@props([
    'type' => 'info',         // success | error | info | warning
    'dismissible' => false,
])
```

- success: `bg-success-50 border-success-200 text-success-800` + `check-circle` icon
- error: `bg-danger-50 border-danger-200 text-danger-800` + `x-circle` icon
- info: `bg-info-50 border-info-200 text-info-800` + `information-circle` icon
- warning: `bg-warning-50 border-warning-200 text-warning-800` + `exclamation-triangle` icon
- `dismissible` 時に × ボタン表示（JS で fade-out）

#### `<x-flash>`

```blade
{{-- layouts/app.blade.php の <main> 冒頭で呼ぶ --}}
<x-flash />
```

セッションに入った flash 値を読んで `<x-alert>` を自動表示する、レイアウト共通の表示口（各 Feature の Blade で個別実装しない、すべての session put → このコンポーネントで描画）。

拾うセッションキー:

| キー | type | 用途 |
|---|---|---|
| `session('success')` | success | Action / Controller からの成功フラッシュ（`return redirect()->back()->with('success', '...')`） |
| `session('error')` | error | 失敗フラッシュ（`->with('error', '...')`） |
| `session('info')` | info | お知らせ系 |
| `session('warning')` | warning | 注意系 |
| `session('status')` | success | **Fortify 標準のステータスメッセージ**（パスワードリセット送信完了 / メール認証完了 等）。`session('success')` と同 variant で表示 |

### Navigation

サイドバーやページ内ナビ系。サイドバーの **メニュー項目構成** はロール別に [frontend-ui-foundation.md](./frontend-ui-foundation.md) で定義。本セクションは「コンポーネント API」のみ。

#### `<x-nav.sidebar>` / `<x-nav.item>` / `<x-nav.section>`

```blade
<x-nav.sidebar>
    <x-nav.item route="dashboard.index" icon="home" label="ダッシュボード" />
    <x-nav.section title="学習" />
    <x-nav.item route="contents.index" icon="book-open" label="教材" />
    <x-nav.item route="mock-exams.index" icon="clipboard-document-check" label="模試" :badge="$unfinishedSessionCount" />
</x-nav.sidebar>
```

```php
{{-- x-nav.item --}}
@props([
    'route',                 // required, ルート名
    'icon' => null,          // Heroicons 名（outline 固定）
    'label',                 // required
    'badge' => null,         // 数値 or null（null なら非表示）
    'active' => null,        // null なら request()->routeIs($route . '*') で自動判定
])
```

- `Route::has($route)` 内部チェック、未登録ルートは **非表示**（自動）
- `active` 未指定時は `request()->routeIs($route . '*')` で自動判定
- アクティブ時に `bg-gray-100 text-gray-900 font-semibold`
- `badge` あり時に右側にピル状の数値表示（`<x-badge variant="danger" size="sm">`）

```php
{{-- x-nav.section --}}
@props(['title'])
```

`<hr>` + `<h6 class="text-xs uppercase text-gray-500">` のセクション区切り。

#### `<x-paginator>`

```blade
<x-paginator :paginator="$users" />
```

Laravel の `LengthAwarePaginator` を受け取り、Tailwind 風ページネーション UI を表示。`paginator` 側で `withQueryString()` 済を前提（query string 引き継ぎ）。

実装方針:
- 内部は Laravel 標準の `{{ $paginator->links('pagination::tailwind') }}` を直呼びするか、`php artisan vendor:publish --tag=laravel-pagination` で公開した `vendor/pagination/tailwind.blade.php` をプロジェクトトークン（`primary` / `gray` 等）に置換する方針のいずれか
- 独自に `<a>` ループを組まない（Laravel 提供の paginator HTML を base にして、見た目だけセマンティックトークンに揃える）

#### `<x-breadcrumb>`

```blade
<x-breadcrumb :items="[
    ['label' => 'ホーム', 'href' => route('dashboard.index')],
    ['label' => '教材', 'href' => route('contents.index')],
    ['label' => $part->title],  {{-- 最終項目は href なし（現在地） --}}
]" />
```

セパレータ `/`、最終項目はリンクなしで `text-gray-500` + `aria-current="page"`。

#### `<x-tabs>`

```blade
<x-tabs :tabs="[
    'catalog' => '資格カタログ',
    'enrolled' => '受講中',
]" :active="$tab" />
```

URL `?tab=catalog` 形式で動作、active タブは `border-b-2 border-primary-600 font-semibold`。

### Tables

#### `<x-table>` / `<x-table.row>` / `<x-table.heading>` / `<x-table.cell>`

```blade
<x-table>
    <x-slot:head>
        <x-table.row>
            <x-table.heading>名前</x-table.heading>
            <x-table.heading>ロール</x-table.heading>
            <x-table.heading>ステータス</x-table.heading>
            <x-table.heading class="text-right">操作</x-table.heading>
        </x-table.row>
    </x-slot:head>
    @foreach ($users as $user)
        <x-table.row>
            <x-table.cell>{{ $user->name }}</x-table.cell>
            <x-table.cell><x-badge>{{ $user->role->label() }}</x-badge></x-table.cell>
            <x-table.cell><x-badge variant="success">{{ $user->status->label() }}</x-badge></x-table.cell>
            <x-table.cell class="text-right">
                <x-dropdown>...</x-dropdown>
            </x-table.cell>
        </x-table.row>
    @endforeach
</x-table>
```

- `<x-table>`: `bg-white rounded-lg overflow-hidden border border-gray-200`
- `<x-table.row>`: hover で `bg-gray-50`
- `<x-table.heading>`: `<th scope="col" class="text-left text-xs uppercase text-gray-500 px-4 py-3">`
- `<x-table.cell>`: `<td class="px-4 py-3">`

## レイアウト継承の例

```blade
@extends('layouts.app')

@section('title', '受講中資格一覧')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '受講中資格'],
    ]" />

    <h1 class="text-2xl font-bold mt-4">受講中資格一覧</h1>

    <x-card class="mt-6">
        {{-- ... --}}
    </x-card>
@endsection

@push('scripts')
    {{-- 必要時のみ、Vite で個別 import 不可な特殊スクリプト --}}
@endpush
```

## やってはいけないこと

- Blade テンプレート内に複雑なロジック（if のネスト、計算）を書かない → Service / ViewModel に逃がす
- N+1: ループ内で `$item->relation->name` が出たら ViewModel / Eager Loading を見直す
- 直接 SQL / `Model::query()` を Blade 内で呼ばない（Controller でデータを整えて渡す）
- 共通コンポーネントの **API 仕様を勝手に変更しない**（本ドキュメントを先に更新する）
- 同じ utility class 組合せが 3 箇所以上で出たら **コンポーネント化する**（`<x-button>` / `<x-card>` 等）
- インライン `style="..."` は使わない（特殊ケース・PDF レイアウトのみ許容）
- Alpine.js / Livewire は使わない（[tech.md](../../docs/steering/tech.md) 規約）。インタラクションは素の JS（[frontend-javascript.md](./frontend-javascript.md)）で実装
