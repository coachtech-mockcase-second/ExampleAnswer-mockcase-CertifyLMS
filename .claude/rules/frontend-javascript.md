---
paths:
  - "提供プロジェクト/resources/js/**"
  - "提供プロジェクト/vite.config.js"
  - "提供プロジェクト/package.json"
  - "模範解答プロジェクト/resources/js/**"
  - "模範解答プロジェクト/vite.config.js"
  - "模範解答プロジェクト/package.json"
---

# 素のJavaScript 規約（Vite ビルド）

## ディレクトリ構成

```
resources/js/
├── app.js                  # エントリ（共通読み込み）
├── csrf.js                 # CSRF トークン取得ヘルパー
├── mock-exam/              # Feature 単位
│   ├── timer.js            #   時間制限カウントダウン
│   └── submit.js           #   解答提出
├── chat/                   # 非同期チャット
│   └── poll.js
└── utils/                  # 共通ユーティリティ
    └── fetch-json.js       #   fetch wrapper（CSRF / エラー処理）
```

## Vite ビルド

- `vite.config.js` で `resources/js/app.js` と `resources/css/app.css` をエントリ指定
- Blade では `@vite(['resources/css/app.css', 'resources/js/app.js'])`
- 開発: `npm run dev`、本番: `npm run build`

## 必須事項

- **Alpine.js / Vue / React は使わない**（素のJSのみ）
- DOM 操作は `document.querySelector` / `addEventListener` で完結
- API 呼び出しは `fetch` + CSRF トークン
- リアクティブ性が必要な箇所は手動で DOM 更新（mock-exam タイマー等）
- 副作用が大きい箇所は関数化、グローバル変数を避ける

## fetch + CSRF テンプレート

```javascript
// resources/js/utils/fetch-json.js
export async function postJson(url, data) {
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrf,
      'Accept': 'application/json',
    },
    body: JSON.stringify(data),
  });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }
  return response.json();
}
```

## mock-exam タイマーの例

```javascript
// resources/js/mock-exam/timer.js
const endAt = new Date(document.querySelector('#exam-end-at').dataset.endAt);
const display = document.querySelector('#timer');
const form = document.querySelector('#mock-exam-form');

const interval = setInterval(() => {
  const remaining = endAt - Date.now();
  if (remaining <= 0) {
    clearInterval(interval);
    form.submit();
    return;
  }
  const min = Math.floor(remaining / 60000);
  const sec = Math.floor((remaining % 60000) / 1000);
  display.textContent = `${min}:${String(sec).padStart(2, '0')}`;
}, 1000);
```

## Advance での SPA / リアルタイム化

- Sanctum で API 認証（cookies-based）
- 自前 SPA: 素の JS + `fetch` でCRUD
- リアルタイムチャット: Pusher + `laravel-echo` を `resources/js/echo.js` から読み込み
- 必要に応じて Vue / React を導入するチケットも検討（Advance 範囲）
