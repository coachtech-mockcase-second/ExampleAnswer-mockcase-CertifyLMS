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
│   ├── Http/{Entity}/{Action}Test.php         # Controller 単位、Entity ディレクトリで Feature 配下にグルーピング
│   ├── Middleware/{Middleware}Test.php        # Middleware 単位
│   └── UseCases/{Entity}/{Action}ActionTest.php  # Action 単位（複雑なケース）
└── Unit/
    ├── Services/{Feature}ServiceTest.php
    ├── UseCases/{Entity}/{Action}ActionTest.php
    ├── Repositories/{Source}RepositoryTest.php
    └── Policies/{Entity}PolicyTest.php
```

### ロール別ディレクトリは禁止（`backend-http.md` namespace 方針と整合）

Controller のロール別 namespace 禁止と同じ理由でテストパスも **ロール別ディレクトリ禁止**。`tests/Feature/Http/Admin/` `Coach/` `Student/` のような階層は作らない。

| パターン | 例 | 採用可否 |
|---|---|---|
| Entity ベース (フラット) | `tests/Feature/Http/Plan/PlanControllerTest.php` / `tests/Feature/Http/User/IndexTest.php` | ✅ |
| ロール別 + Entity | `tests/Feature/Http/Admin/Plan/PlanControllerTest.php` | ❌ 禁止 |
| Feature 単位 (多 Controller の Feature) | `tests/Feature/Http/MeetingQuota/CheckoutControllerTest.php` / `tests/Feature/Http/MeetingPack/MeetingPackControllerTest.php` | ✅ |
| 領域別 (外部連携) | `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php` | ⚪ 許容 (Controller 側 `Webhooks\` namespace と対応) |

namespace 宣言も同じく `Tests\Feature\Http\{Entity}` で、`Tests\Feature\Http\Admin\{Entity}` は禁止。

> 認可テスト (admin のみ通過 / coach 403 等) は **Policy で判定するロジックの検証** であって、テストファイルの **配置とは別レイヤー**。「admin のテストだから Admin/ 配下に」という発想は規約違反。同じ Entity の認可テストは同じディレクトリにまとめる。

## ドメイン例外テスト: JSON / HTML の使い分け

`Handler.php` が HttpException(409 / 422)を HTML リクエスト時 `redirect()->back()->with('error', ...)` に変換する仕様(`backend-exceptions.md` 「Handler によるブラウザ向け redirect+flash 変換」参照)に合わせて、テストでは以下を使い分ける:

| 検証したいこと | 使うメソッド | assert 例 |
|---|---|---|
| **ドメイン例外の HTTP ステータス**(状態違反 / 削除不可 / 残数不足等で 409 / 422 が返ることの保証) | `deleteJson()` / `postJson()` / `patchJson()` / `putJson()` | `$response->assertStatus(409)` / `$this->assertSame(409, $response->status())` |
| **HTML 経由 redirect+flash 挙動**(ブラウザから削除ボタン押下 → 元画面に戻る + 赤 Alert 表示の保証) | `->from(route(...))->delete(route(...))` 等(リファラ設定 + 通常の HTML リクエスト) | `$response->assertRedirect(route(...))` + `$response->assertSessionHas('error')` |
| **FormRequest バリデーション失敗**(422 を返すが Handler 変換対象外、`ValidationException` 経路) | どちらでも OK(`postJson` 推奨で素直に書ける) | `$response->assertStatus(422)` |
| **Policy 拒否 (403)**(`$this->authorize()` 失敗 / 他ロールアクセス) | どちらでも OK(Handler 変換対象外、`assertForbidden` がそのまま使える) | `$response->assertForbidden()` |

### サンプル実装

```php
// JSON 経由(status code 検証、テスト主目的が「拒否されることの保証」)
public function test_destroy_returns_409_for_published_plan(): void
{
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->published()->create();

    $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

    $this->assertSame(409, $response->status());
    $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
}

// HTML 経由(redirect + flash 検証、テスト主目的が「ブラウザ UX が正しいことの保証」)
public function test_destroy_via_browser_redirects_back_with_flash_error_for_published_plan(): void
{
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->published()->create();

    $response = $this->actingAs($admin)
        ->from(route('admin.plans.show', $plan))
        ->delete(route('admin.plans.destroy', $plan));

    $response->assertRedirect(route('admin.plans.show', $plan));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
}
```

### 書き分けの判断軸

- **同じドメイン例外を JSON と HTML の両方で検証する必要はない**(Handler のロジックが共通なので片方で十分)
- 既存テストで `assertStatus(40x)` / `assertSame(40x, ...)` を書くなら **JSON 経由**(`->deleteJson` 等)に書き換える(これが既存パターンとの整合性も高い)
- **ブラウザ実機の UX(リダイレクト先 + flash メッセージ)を保証したい** 場合のみ、追加で HTML 経由テストを 1 件書く(`Plan` / `MeetingPack` のテストにサンプル実装あり)
- 全 Feature の全ドメイン例外で HTML 経由テストを書く必要はない(Handler の挙動は共通なので、代表 1 件で十分)

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

## Feature(Http) と Feature(UseCases) の責務分離

`tests/Feature/Http/{Entity}/{Action}Test.php` と `tests/Feature/UseCases/{Entity}/{Action}ActionTest.php` は **役割を分離** する。両方を書く Action もあるが、書く内容は重複させない。

### Feature(Http) — Controller 経由の統合テスト

**対象**: HTTP リクエスト → 認可 → FormRequest バリデーション → Action 呼出 → リダイレクト / ビュー描画 / DB 反映の一連の流れ。

**書く内容**:
- 認可漏れの検証（他ロール / 他人リソース / 未ログインで 403 / 404 / 302）
- FormRequest バリデーション失敗（必須項目欠落 / 型不一致 / Enum 値外）の HTTP レスポンス
- 正常系の **代表 1-2 ケース**（status code + `assertDatabaseHas` で DB 反映確認 + redirect 先確認）
- Flash メッセージ / リダイレクト先 / ビューデータの確認
- CSRF / Middleware（`auth` / `role:student` / `EnsureActiveLearning` 等）の効きを検証

### Feature(UseCases) — Action 単体の業務ロジックテスト

**対象**: HTTP 文脈を介さず、Action を直接 `__invoke()` した時の業務ロジック動作。

**書く内容**:
- 複雑な業務分岐の網羅（例: `IssueInvitationAction` の「新規」「既存 invited + pending」「既存 invited + force=true」「既存 active」の 4 分岐）
- 例外パスの検証（独自例外が正しく throw されるか、各分岐ごとに）
- DB トランザクション原子性（途中で例外発生時に部分書込が残らないこと）
- 副作用検証（Mail / Notification / Schedule Command 起動 / 外部 Service 呼出）
- 冪等性（同条件で 2 回呼んだ時の挙動）
- DB スナップショット（Action 前後で関係テーブルの状態が想定通り）

### 棲み分けの判断軸

| シナリオの性質 | Feature(Http) | Feature(UseCases) |
|---|---|---|
| 認可漏れ（他者 403 等） | ✅ | ❌（Action は HTTP 認可を持たない） |
| FormRequest バリデーション失敗 | ✅ | ❌（Action は validated array を受け取るだけ） |
| 正常系 1 ケース（status + DB + redirect） | ✅ | （複雑なら ✅、単純なら不要） |
| 業務分岐の網羅 | ❌（Http で書くと冗長） | ✅ |
| 例外パスの細かい検証 | ❌（代表 1 ケースを Http で）| ✅ |
| 副作用検証（Mail / Notification 等） | （HTTP 経由で 1 ケース） | ✅（細かいケース別検証） |
| DB トランザクション原子性 | ❌ | ✅ |

### 簡易ケースで UseCases テストを省略する判断

Action が単純（1-2 行で `Model::create()` を呼ぶだけ 等）の場合、`tests/Feature/UseCases/` は **書かなくてよい**。`tests/Feature/Http/` の代表 1 ケースで DB 反映を確認すれば十分。

書くべき判断軸: **業務分岐が 2 つ以上 / 例外パスが 2 つ以上 / 副作用が複数（DB + Mail + Notification 等）** のいずれかを満たす場合、`tests/Feature/UseCases/` を書く。

### 二重で書かない原則

「正常系の status code + DB 反映確認」を **両方に書くのは禁止**（DRY 違反）。Feature(Http) で書いたら Feature(UseCases) では別の業務分岐 / 例外パスを検証する役割に絞る。
