---
paths:
  - "提供プロジェクト/tests/**"
  - "模範解答プロジェクト/tests/**"
---

# テスト規約（PHPUnit）

## 必須

- すべての Controller / Action に Feature テスト
- すべての Service / Repository に Unit テスト
- `use RefreshDatabase` + `actingAs($user)` を基本パターン
- ファクトリで前提データを構築
- **Action ファイル新規作成・修正時は対応テストを同ターンで実装**

## 配置

```
tests/
├── Feature/
│   ├── Auth/{Flow}Test.php
│   ├── Http/{Entity}/{Action}Test.php         # Controller 単位
│   └── UseCases/{Entity}/{Action}ActionTest.php  # Action 単位（複雑なケース）
└── Unit/
    ├── Services/{Feature}ServiceTest.php
    ├── UseCases/{Entity}/{Action}ActionTest.php
    ├── Repositories/{Source}RepositoryTest.php
    └── Policies/{Entity}PolicyTest.php
```

## 必須シナリオ（カテゴリ別）

| カテゴリ | 必須テスト |
|---|---|
| 取得系（index/show）| 正常系（フィルタ含む）+ 認可漏れ（他者リソースアクセスで403/404）|
| 登録系（store）| 正常系 + バリデーション失敗 + 認可漏れ |
| 更新/削除系 | 正常系 + 認可漏れ + 他リソース非更新確認（DBスナップショット）|
| ロール固有機能 | 各ロール（admin/coach/student）の挙動分岐 |
| 状態遷移 | 全遷移パス + 不正遷移時の例外 |

## Feature テスト テンプレート

```php
<?php

namespace Tests\Feature\Http\Enrollment;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Enrollment;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_own_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        $response = $this->actingAs($student)
            ->put(route('enrollments.update', $enrollment), [
                'exam_date' => '2026-12-01',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'exam_date' => '2026-12-01',
        ]);
    }

    public function test_student_cannot_update_others_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $otherEnrollment = Enrollment::factory()->create();

        $response = $this->actingAs($student)
            ->put(route('enrollments.update', $otherEnrollment), [
                'exam_date' => '2026-12-01',
            ]);

        $response->assertForbidden();
    }
}
```

## 必須事項

- 1テストメソッド = 1シナリオ（メソッド名で日本語的に表現可、`test_*` プレフィクス）
- `assertDatabaseHas` / `assertDatabaseMissing` で DB 確認
- 他リソース非影響を `assertDatabaseHas` で別レコードも確認（必要に応じて）
- ロール別テストは `User::factory()->admin()` / `coach()` / `student()` ステートで切替
- Factory に `state()` を充実させる
