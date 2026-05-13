---
paths:
  - "提供プロジェクト/resources/views/**"
  - "模範解答プロジェクト/resources/views/**"
---

# Blade テンプレート規約

## ディレクトリ構成

```
resources/views/
├── layouts/                # 共通レイアウト
│   ├── app.blade.php       #   ログイン後の主レイアウト
│   └── guest.blade.php     #   未ログイン
├── components/             # Blade コンポーネント（再利用UI）
│   ├── button.blade.php
│   ├── form/input.blade.php
│   └── modal.blade.php
├── auth/                   # 認証系（Fortify）
├── dashboard/              # ダッシュボード
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
- 動的データは `{{ $var }}`（自動エスケープ）、HTML埋め込みは `{!! !!}`（XSS注意）

## 例: フォームテンプレート

```blade
<form method="POST" action="{{ route('enrollments.update', $enrollment) }}">
    @csrf
    @method('PUT')

    <x-form.input
        name="exam_date"
        label="目標受験日"
        type="date"
        :value="old('exam_date', $enrollment->exam_date?->format('Y-m-d'))"
        required
    />

    @error('exam_date')
        <p class="text-red-600 text-sm">{{ $message }}</p>
    @enderror

    <x-button type="submit">更新</x-button>
</form>
```

## レイアウト継承

```blade
@extends('layouts.app')

@section('title', '受講中資格一覧')

@section('content')
    <h1 class="text-2xl font-bold">受講中資格一覧</h1>
    {{-- ... --}}
@endsection
```

## やってはいけないこと

- Blade テンプレート内に複雑なロジック（if のネスト、計算）を書かない → Service / ViewModel に逃がす
- N+1: ループ内で `$item->relation->name` が出たら ViewModel / Eager Loading を見直す
- 直接 SQL / Model::query() を Blade 内で呼ばない（Controller でデータを整えて渡す）
