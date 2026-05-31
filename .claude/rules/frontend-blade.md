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
│   ├── pdf.blade.php       #   mpdf 用（共通レイアウト非継承、インライン <style>）
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
│   ├── content-management/ #   Feature 横断の共有 UI（<x-content-management.status-pill> / publish-confirm-modal / delete-confirm-modal）
│   └── ...
├── auth/                   # 認証系（Fortify、[[auth]] 所有）
├── errors/                 # エラーページ（[frontend-ui-foundation.md] 所有）
├── dashboard/              # ダッシュボード（[[dashboard]] 所有、ロール別 Blade を直下に保持）
│   ├── admin.blade.php
│   ├── coach.blade.php
│   └── student.blade.php
└── {entity}/               # ★ Entity 単位（単数 kebab-case、Eloquent Model 名と 1:1 対応）
    ├── index.blade.php     #   受講生・コーチ日常利用（catalog / 閲覧 / 操作）
    ├── show.blade.php
    ├── _partials/          #   Entity 内共有 partials
    ├── _modals/            #   Entity 内モーダル
    ├── {sub-feature}/      #   Entity 内のサブ機能（goals / notes 等）任意
    ├── management/         #   admin or admin+coach の管理操作（CRUD / 状態遷移 / 削除 / モデレーション / 監査）
    │   ├── index.blade.php
    │   ├── show.blade.php
    │   ├── create.blade.php
    │   └── edit.blade.php
    └── coach/              #   coach 特化（自分のスコープ内の閲覧/メモ操作、management より権限狭い）任意
        ├── index.blade.php
        └── show.blade.php
```

### Entity 命名規則（必読）

トップディレクトリ名は **Eloquent Model 名の単数 kebab-case** に揃える。route 名 / URL とは **意図的にずれる**（後述）。

| Model | views ディレクトリ | URL prefix | route 名（変更なし）|
|---|---|---|---|
| `User` | `user/` | `/admin/users` | `admin.users.*` |
| `Certification` | `certification/` | `/admin/certifications`, `/certifications` | `admin.certifications.*` / `certifications.*` |
| `MockExam` | `mock-exam/` | `/admin/mock-exams`, `/mock-exams` | `admin.mock-exams.*` / `mock-exams.*` |
| `MockExamQuestion` | `mock-exam-question/` | `/admin/mock-exams/{mockExam}/questions` | `admin.mock-exams.questions.*` |
| `MockExamSession` | `mock-exam-session/` | `/admin/mock-exam-sessions`, `/mock-exam-sessions` | `admin.mock-exam-sessions.*` / `mock-exam-sessions.*` |
| `Enrollment` | `enrollment/` | `/admin/enrollments`, `/enrollments` | `admin.enrollments.*` / `enrollments.*` |
| `EnrollmentGoal` | `enrollment-goal/` | `/enrollments/{enrollment}/goals` | `enrollments.goals.*` |
| `EnrollmentNote` | `enrollment-note/` | `/admin/enrollments/{enrollment}/notes` | `admin.enrollments.notes.*` |
| `ChatRoom` | `chat-room/` | `/chat-rooms`, `/admin/chat-rooms` | `chat.*` / `admin.chat-rooms.*` |
| `QaThread` | `qa-thread/` | `/qa-board`, `/admin/qa-board` | `qa-board.*` / `admin.qa-board.*` |
| `Plan` | `plan/` | `/admin/plans` | `admin.plans.*` |
| `MeetingPack` | `meeting-pack/` | `/admin/meeting-packs` | `admin.meeting-packs.*` |
| `Announcement` | `announcement/` | `/admin/announcements` | `admin.announcements.*` |
| `Part` / `Chapter` / `Section` / `SectionQuestion` / `QuestionCategory` | `part/` `chapter/` `section/` `section-question/` `question-category/` | `/admin/...` 各種 | `admin.parts.*` 等 |

ポイント:

- **view 名と route 名は意図的にずれる**: route 名 / URL prefix は外向け（受講生・コーチ・admin にとっての URL 慣習として `/admin/...` の階層が意味を持つ）なので **変更しない**。view 名 / Class 名は内向け（開発者用）なので **Entity + 役割** の純粋な命名軸に揃える
- **子 Entity も完全フラット top-level**: `MockExamQuestion` は `mock-exam/questions/` のような nested ではなく、`mock-exam-question/` 単独で並べる。BookStack / Snipe-IT / Akaunting 等の Laravel 大型 OSS と整合（`books/` `chapters/` `pages/` のフラット展開がデファクト）
- **Feature 単位ディレクトリは top-level に作らない**: `content-management/` のような複数 Entity 束ね名は使わない。複数 Entity 共有の partials/modals は **Feature 名前空間付きの Blade コンポーネント**（`resources/views/components/{feature}/` → `<x-{feature}.xxx>`、例: `<x-content-management.status-pill>` / `<x-content-management.publish-confirm-modal>`）にまとめる

### 役割サブディレクトリ命名規則

| サブディレクトリ | 用途 | 例 |
|---|---|---|
| **`management/`** | admin or admin+coach の管理操作。CRUD / 状態遷移 / 削除 / モデレーション / 監査を統合 | `user/management/`（admin の user CRUD）/ `chat-room/management/`（admin の chat 監査）/ `qa-thread/management/`（admin モデレーション）/ `mock-exam-session/management/`（admin の閲覧専用） |
| **`coach/`** | coach 特化の閲覧/メモ操作（`management/` より権限が狭い） | `enrollment/coach/`（coach の担当受講生一覧）/ `meeting/coach/`（coach の担当面談） |
| **トップ直下** | 受講生・コーチが日常利用する画面（catalog / 閲覧 / 操作） | `certification/index.blade.php`（受講生 catalog）/ `chat-room/show.blade.php`（受講生・コーチ chat 閲覧）/ `enrollment/show.blade.php`（受講生 enrollment 詳細） |
| **`_partials/` / `_modals/`** | Entity 内共有 partials / モーダル | `enrollment/_partials/` |
| **`{sub-feature}/`**（任意） | Entity 内のサブ機能区切り | `enrollment/goals/` / `enrollment/notes/` |

### ❌ 採用しないパターン（ロール由来 top-level / Class 命名）

```
views/
├── admin/                # ❌ ロール由来 top-level は禁止
│   ├── users/
│   ├── enrollments/
│   └── ...
├── coach/                # ❌ 同上
│   ├── students/
│   └── meetings/
└── student/              # ❌ 同上
    └── enrollments/
```

理由（[backend-http.md](./backend-http.md)「ロール別 namespace 禁止」と同じ精神を view 階層にも適用）:

- **リソース固有認可は Policy で分岐すべき**: ロール別ディレクトリで切ると Policy の責務が view ディレクトリ階層に漏れ出す
- **ロール追加・移管時に大規模リネームが必要**: admin → coach への業務移管・新ロール追加時、ディレクトリ全体を物理移動することになる
- **同 Entity を複数ロールが操作する場合に二重実装が生まれる**: `admin/certifications/show.blade.php` と `coach/certifications/show.blade.php` のような重複が発生し、`@can` で出し分ければ済むはずの分岐がディレクトリ構造で表現されてしまう
- **「admin が触る画面 → admin/」ではなく「Entity 操作の意味で management/」が正しい命名軸**: 「誰が触るか」ではなく「何をする操作か」で分類する

#### Controller / UseCase namespace でも同じ精神

```php
// ❌ 採用しない（ロール由来 Class 命名）
class AdminEnrollmentController { ... }
class CoachStudentController { ... }
namespace App\UseCases\AdminAnnouncement;
namespace App\Http\Requests\AdminChatRoom;

// ✅ 採用する（機能由来 / Entity 単位 namespace）
class EnrollmentManagementController { ... }
class EnrollmentRosterController { ... }              // coach の担当 roster
class ChatRoomModerationController { ... }            // admin の chat 監査
namespace App\UseCases\Announcement;                  // Entity 単位
namespace App\UseCases\Chat\Moderation;               // 既存 Entity namespace + サブ namespace
```

詳細は [backend-http.md](./backend-http.md) の「namespace 方針」を参照。

### 共通画面で admin/coach の出し分けが必要な場合

同じ Entity を複数ロールが操作する画面（`certification/management/show.blade.php` のように admin + coach が共有する画面）では、view ファイルそのものをロール別に複製せず、Blade 内 `@can('update', $cert)` 等で個別判定し、UI を出し分ける。詳細は [frontend-ui-foundation.md](./frontend-ui-foundation.md) の「複数ロール共通画面の 4 層認可」も参照。

## 必須事項

- `@csrf` トークンはすべてのフォームに必須
- すべての `<form>` に `novalidate` を付与（ブラウザ標準バリデーションを抑止し、FormRequest のサーバーサイドメッセージを表示させる。`<x-form.*>` 内の `required` / `type` / `maxlength` 等は a11y・UX のため残す）
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

### `layouts/pdf.blade.php`（mpdf 専用）

- `app.blade.php` を **継承しない**（mpdf は外部 CSS / JS を解釈しないため、インライン `<style>` のみ）
- 日本語は mpdf の CJK フォント（`IPAGothic` 等）を `font-family` で指定
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

## ユーザー向け文言の規約（重要）

Blade で **画面に描画される日本語テキスト** (`<p>` / `<dt>` / `<label>` / `<button>` 本文 / `<x-form.input :hint>` / モーダルの説明文 / `placeholder` / flash メッセージ / バリデーションメッセージ等) には、**DB スキーマやコードベース内部の機械可読な用語を露出させない**。受講生 / コーチ / 管理者の誰が読んでも、本 LMS の業務ドメイン語彙で自然に理解できる文言にする。

`backend-types-and-docblocks.md` の「コードコメントで使わない構築側メタ情報」が **Claude / 開発者が読む PHP コメント** を対象にするのに対し、本ルールは **受講生・コーチ・管理者がブラウザで読むテキスト** を対象にする。**ユーザー画面に出る方が影響が大きいため、PHP コメントよりも厳しく適用する**。

### 禁止される露出

機械可読な内部用語をユーザー向けテキストに含めない:

| カテゴリ | 禁止例 | 業務用語への置換 |
|---|---|---|
| **Model クラス名** | `UserStatusLog` / `UserPlanLog` / `MeetingQuotaTransaction` / `EnrollmentStatusLog` / `Enrollment` | ステータス変更履歴 / プラン履歴 / 面談回数履歴 / 受講登録 |
| **DB テーブル名** | `user_status_logs` / `meeting_quota_transactions` / `enrollment_status_logs` | 同上 |
| **Enum 値** (snake_case 機械値) | `admin_grant` / `granted_initial` / `status_change` / `renewed` / `assigned` | 管理者による付与 / 初期付与 / ステータス変更 / プラン延長 / プラン割当 |
| **カラム名** | `granted_by_user_id` / `event_type` / `plan_expires_at` / `changed_by_user_id` | 操作者 / イベント種別 / プラン有効期限 / 操作者(変更者) |
| **構築側ファイルパス** | `docs/specs/` / `.claude/rules/` / `app/Models/Foo.php` | (そもそも書かない) |
| **改修フェーズ用語** | `v3 改修` / `2026-05-XX` / `Step N` / `Phase X` | (そもそも書かない、または「最新仕様で」等) |

### 良例 / 悪例

```blade
{{-- ❌ 悪い(Model 名と Enum 機械値が露出) --}}
<p class="text-sm text-ink-700">
    トラブル補填等の目的で面談回数を手動付与します。
    <span class="font-semibold">MeetingQuotaTransaction</span> に
    <span class="font-mono">admin_grant</span> として記録されます。
</p>

{{-- ✅ 良い(業務用語のみ) --}}
<p class="text-sm text-ink-700">
    トラブル補填 / キャンペーン付与等の目的で、面談回数を手動付与します。
    面談回数履歴に「管理者による付与」として記録され、操作者があなたとして残ります。
</p>
```

```blade
{{-- ❌ 悪い(Model 名が露出) --}}
<p>退会理由は監査ログ（UserStatusLog）に記録されます。</p>

{{-- ✅ 良い --}}
<p>退会理由はステータス変更履歴に固定記録されます。</p>
```

```blade
{{-- ❌ 悪い(Model 名 + 改修フェーズ用語) --}}
<p>UserPlanLog に「v3 で追加された renewed イベント」として記録されます。</p>

{{-- ✅ 良い --}}
<p>プラン履歴に「プラン延長」として記録されます。</p>
```

### 適用範囲外（PHP コード文脈は OK）

以下は **画面に描画されない** ため本ルールの対象外。`@php` ブロック / `@props` / Controller / Service と同じ「PHP コード文脈」として扱う:

- `@php use App\Enums\MeetingQuotaTransactionType; @endphp` の import 文
- `match ($type) { MeetingQuotaTransactionType::AdminGrant => 'success' }` の PHP マッチ式（**戻り値が `<x-badge>` の `variant` 等の machine 属性なら OK**、戻り値がユーザー画面に出る文字列なら label() 経由で日本語化）
- `$user->plan_expires_at?->format('Y-m-d')` のプロパティアクセス（**レンダリングされるのは日付値、カラム名ではない**）
- `data-modal-trigger="extend-course-modal"` 等の HTML 属性値（**画面に描画されない**）
- HTML id / class 名（`id="grant-meeting-quota-form"`）
- form の `name="amount"` 属性（HTTP リクエストキー、画面に出ない）

判断軸: **「そのテキストはブラウザの画面に文字として描画されるか?」**
- Yes → 業務用語必須
- No (HTML 属性 / PHP 変数 / Enum value 経由でロジック判定する match 戻り値) → 機械値で OK

### Enum を画面表示する場合

Enum 値 (`$status->value` で得られる snake_case) を直接 echo してはいけない。**必ず `label()` メソッドで日本語化**してから描画する:

```blade
{{-- ❌ 悪い(snake_case が画面に出る) --}}
{{ $status->value }}            {{-- in_progress --}}

{{-- ✅ 良い(label() で日本語化) --}}
{{ $status->label() }}          {{-- 受講中 --}}
```

Enum クラス側に `label(): string` を必ず実装する。`backend-models.md` の Enum 規約と整合。

### 機械的チェック

CI / 開発時の grep で機械検出可能:

```bash
# 各 Feature 実装後・モーダル追加後にローカルで確認推奨
grep -rnE 'UserStatusLog|UserPlanLog|MeetingQuotaTransaction|EnrollmentStatusLog|admin_grant|granted_initial|status_change' resources/views/ \
  | grep -v '@php\|use App\\Enums\|use App\\Models\|->cases()\|::class\|match \(' \
  | grep -v 'id="\|name="\|data-\|class="'
```

検出結果が空でなければ、上記「禁止される露出」リストで業務用語に置換する。

### ドメイン語彙の SSoT

業務用語の整合は `docs/steering/product.md` の語彙(受講生 / コーチ / 管理者 / プラン / 招待 / 面談 / 修了証 / ステータス変更履歴 / プラン履歴 / 面談回数履歴 等)を SSoT とする。新規業務用語が必要になった場合は先に product.md に追加してから Blade に反映する。

## やってはいけないこと

- Blade テンプレート内に複雑なロジック（if のネスト、計算）を書かない → Service / ViewModel に逃がす
- N+1: ループ内で `$item->relation->name` が出たら ViewModel / Eager Loading を見直す
- 直接 SQL / `Model::query()` を Blade 内で呼ばない（Controller でデータを整えて渡す）
- 共通コンポーネントの **API 仕様を勝手に変更しない**（本ドキュメントを先に更新する）
- 同じ utility class 組合せが 3 箇所以上で出たら **コンポーネント化する**（`<x-button>` / `<x-card>` 等）
- インライン `style="..."` は使わない（特殊ケース・PDF レイアウトのみ許容）
- Alpine.js / Livewire は使わない（[tech.md](../../docs/steering/tech.md) 規約）。インタラクションは素の JS（[frontend-javascript.md](./frontend-javascript.md)）で実装
- **ユーザー向けテキストに DB / Model / Enum 機械値を露出しない**（本ドキュメント「ユーザー向け文言の規約」参照）

## Basic / Advance チケットと JS の使い分け

提供 PJ の Blade は受講生に渡り、受講生はそれを足場にバックエンドを実装する。チケット難易度で Blade の JS 依存度を変える。

### Basic チケットの Blade（JS なし）

Basic は「教材内 + ContactForm / BookShelf の範囲」。**JS を使わず純 Laravel（Blade + フォーム POST + リダイレクト）で完結**させる。

- ❌ 使わない: `<x-dropdown>` / `<x-modal>` 等の JS 依存コンポーネント、インライン `<script>`、`data-*` による JS フック
- ✅ 操作の実装:
  - 編集 → 専用ページ（`{entity}/edit` 等）へリンク遷移（インライン編集フォームの JS toggle は使わない）
  - 削除 → `<form method="POST">` + `@method('DELETE')`、誤操作防止は `onsubmit="return confirm()"`（HTML 標準、JS ファイル不要）
  - 操作メニュー → ドロップダウンに畳まず、編集リンク + 削除フォームを直接並べる
- 根拠: BookShelf / ContactForm の Basic パターン（編集=専用ページ / 削除=フォーム POST + confirm / インライン編集なし / inline script なし）

### Advance チケットの Blade（JS 可）

Advance は素の JS（Vite ビルド）+ Sanctum API 等を扱う。動的 UI（非同期更新 / リアルタイム / モーダル）は素の JS（[frontend-javascript.md](./frontend-javascript.md)）で実装してよい。上記「やってはいけないこと」の「Alpine.js / Livewire は使わない、インタラクションは素の JS」は Advance に適用される指針。

## 提供 PJ の Blade コメント方針

提供 PJ の Blade（受講生に渡る）のコメントは、**マークアップ構造（各ブロックの役割）+ フロント実装観点（JS なし / confirm / XSS 表示処理）のみ**を書く。**バックエンド設計には一切触れない** — 受講生が Blade を読んで必要なバックエンドを自力で設計する練習を奪わないため（書くとヒントになり教材価値が下がる）。

| 書く ✅（構造 / フロント観点） | 書かない ❌（バックエンド設計のヒント） |
|---|---|
| `{{-- スレッド本体カード（バッジ + タイトル + 投稿者 + 操作 + 本文）--}}` | `{{-- Controller@show が $thread を渡す。certification を Eager Load で N+1 回避 --}}` |
| `{{-- 削除はフォーム送信 + confirm()（JS 不要）--}}` | `{{-- @can('delete') は QaThreadPolicy::delete に対応 --}}` |
| `{{-- 本文。e() + nl2br で XSS 対策 --}}` | `{{-- POST /qa-board（qa-board.store）、StoreRequest でバリデーション --}}` |

書かない具体カテゴリ: Controller / Action / Service / Policy のクラス・メソッド名、route 定義（`routes/web.php に定義` 等）、Eager Load / withCount / N+1 等のクエリ最適化、バリデーションルール（`max:200` 等）。

### 標準ヘッダ形式（提供 PJ Blade）

構造的に重要な Blade（`layouts/` / 共通コンポーネント / 画面レベル view / 複雑な partial）には先頭にヘッダコメントを付け、受講生が「この画面/部品が何で、どんなブロックで構成されるか」を一目で掴めるようにする。**自明な小 partial は省略可**（What の言い換えはノイズになるので付けない）。

```blade
{{--
    {このファイルの役割を 1 行}
    構成: {主要ブロックを / や → で列挙}
    {フロント観点（あれば）: JS なし（リンク + フォーム POST）/ confirm() / XSS 表示 / タブ動作 等}
--}}
```

- コンポーネント（`components/`）は props / slot の概要も 1 行添える（`@props` の意図を補足、ただし機械的な型の羅列は不要）。
- **バックエンド設計（データ源 / クラス名 / route / クエリ最適化 / バリデーション値）は書かない**（上表の ❌ 準拠）。受講生が Blade から必要な BE を自力設計する余地を残す。
- ⚠️ **コメント本文にリテラル `@php` / `@verbatim` を書かない**（構造を説明する文中で言及する場合も）。Blade は `{{-- --}}` を除去する**前に** `@php…@endphp` ブロックを抽出するため、コメント内の `@php` が直後の実 `@php` ブロックの `@endphp` と対になり、変数定義ごと壊れる（描画時に `Undefined variable` を誘発し 500）。「冒頭の処理で…」「先頭の整形で…」等の自然文に言い換える。
