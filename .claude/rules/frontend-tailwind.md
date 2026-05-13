---
paths:
  - "提供プロジェクト/resources/css/**"
  - "提供プロジェクト/tailwind.config.js"
  - "模範解答プロジェクト/resources/css/**"
  - "模範解答プロジェクト/tailwind.config.js"
---

# Tailwind CSS 規約

## 構成

```
resources/css/
└── app.css   # @tailwind directives エントリ
```

```css
/* resources/css/app.css */
@tailwind base;
@tailwind components;
@tailwind utilities;
```

- `tailwind.config.js` でテーマ拡張・コンテンツパス指定
- `content` には Blade と JS のパスを必ず含める（`./resources/views/**/*.blade.php`, `./resources/js/**/*.js`）

## 必須事項

- **ユーティリティファースト**: 専用 CSS クラスより Tailwind utility 優先
- **コンポーネント化**: 同じ utility の組み合わせが3箇所以上で出たら Blade コンポーネント化（`<x-button>` 等）
- **カスタムクラスは最小限**: `tailwind.config.js` の `theme.extend` で色・スペーシングを追加
- **レスポンシブ**: `sm:` `md:` `lg:` プレフィクスを使う
- **ダークモード**: 不要（採用しない）

## 推奨パターン

### ボタン コンポーネント

```blade
{{-- resources/views/components/button.blade.php --}}
@props(['variant' => 'primary', 'type' => 'button'])

@php
$base = 'inline-flex items-center px-4 py-2 rounded font-semibold transition';
$variants = [
    'primary' => 'bg-blue-600 text-white hover:bg-blue-700',
    'danger' => 'bg-red-600 text-white hover:bg-red-700',
    'secondary' => 'bg-gray-200 text-gray-800 hover:bg-gray-300',
];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "$base {$variants[$variant]}"]) }}>
    {{ $slot }}
</button>
```

### カード レイアウト

```blade
<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-bold mb-4">{{ $title }}</h2>
    <div class="space-y-2">
        {{ $slot }}
    </div>
</div>
```

## やってはいけないこと

- `style="..."` インライン CSS（特殊ケースのみ許容）
- `@apply` の濫用（コンポーネント化で十分なケース）
- グローバル CSS にスタイルを書く（Tailwind utility で表現できる範囲ならそちらで）
