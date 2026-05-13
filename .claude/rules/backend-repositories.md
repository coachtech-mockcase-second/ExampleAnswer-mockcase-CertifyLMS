---
paths:
  - "提供プロジェクト/app/Repositories/**"
  - "模範解答プロジェクト/app/Repositories/**"
---

# Repository 層規約（外部API依存切り離し用、限定採用）

## いつ作るか — 限定

- **外部API（HTTP）依存を切り離す**ときのみ
- 例: Gemini API、Google Calendar API、Pusher、Slack
- **DB 専用には作らない**（Eloquent Model のスコープ・リレーションで十分）

## なぜ限定するか

- Laravel コミュニティ標準ではない（DBに対する Repository は冗長）
- Eloquent Model 自体が「データアクセス抽象化層」を提供している
- 過剰設計を避け、必要箇所のみ採用

## 命名・配置

- 配置: `app/Repositories/{Source}Repository.php`
- インタフェース: `app/Repositories/Contracts/{Source}RepositoryInterface.php`（必要に応じて）
- 命名: `{ExternalSource}Repository`（例: `GeminiRepository`, `GoogleCalendarRepository`）
- ServiceProvider で `bind()` / `singleton()` し DI で注入

## テンプレート

```php
<?php

namespace App\Repositories;

use App\Repositories\Contracts\GeminiRepositoryInterface;
use Illuminate\Support\Facades\Http;

class GeminiRepository implements GeminiRepositoryInterface
{
    public function __construct(private string $apiKey, private string $endpoint) {}

    public function chat(string $prompt, array $history = []): string
    {
        $response = Http::withToken($this->apiKey)
            ->post($this->endpoint, [
                'contents' => $this->buildContents($prompt, $history),
            ])
            ->throw();

        return $response->json('candidates.0.content.parts.0.text');
    }

    private function buildContents(string $prompt, array $history): array { /* ... */ }
}
```

## 必須事項

- インタフェースを切る（テスト時にモック差し替え）
- HTTP 呼び出しは `Illuminate\Support\Facades\Http`（Laravel HTTP Client）を使う
- リトライ・タイムアウト・エラーハンドリングを実装（`->retry(3, 100)->timeout(10)`）
- APIキーは `.env` から `config()` 経由で取得（ハードコード禁止）

## テスト

- `tests/Unit/Repositories/{Source}RepositoryTest.php`
- `Http::fake()` で外部API をスタブ化
- 正常系 + エラーレスポンス系をカバー
