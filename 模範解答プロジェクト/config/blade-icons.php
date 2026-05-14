<?php

/**
 * blade-icons の `<x-icon>` 自動コンポーネント登録を無効化する。
 * 本プロジェクトでは独自の `<x-icon>` を `resources/views/components/icon.blade.php`
 * で定義しており、内部で `<x-dynamic-component>` を経由して
 * blade-heroicons のコンポーネントへ委譲する。
 */
return [
    'components' => false,
];
