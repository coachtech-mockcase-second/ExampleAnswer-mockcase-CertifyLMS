---
paths:
  - "提供プロジェクト/app/Http/**"
  - "提供プロジェクト/routes/**"
  - "模範解答プロジェクト/app/Http/**"
  - "模範解答プロジェクト/routes/**"
---

# HTTP 層規約（Controller / FormRequest / Route / Resource / Middleware）

## Controller — 薄く保つ

- リクエスト受付 → バリデーション・認可は他層へ委譲 → Action / Service 呼び出し → レスポンス整形
- **メソッド内のビジネスロジックは原則 0行**。Controller に if 文や計算が増えたら Service / Action に移す
- 命名: `{Entity}Controller`、リソースコントローラパターン推奨（`index/show/store/update/destroy`）
- 配置: 基本フラット `app/Http/Controllers/{Entity}Controller.php`。namespace 方針は下記「namespace 方針」セクション参照
- **認可は Controller で実施**（`$this->authorize()` または FormRequest の `authorize()`）。Action 内では呼ばない
- **1 Controller method = 1 Action**。Controller method 名と Action クラス名は一致させる（`update()` → `UpdateAction`、`submit()` → `SubmitAction`）。`backend-usecases.md` 参照

### namespace 方針

Controller は **基本フラット** (`app/Http/Controllers/{Entity}Controller.php` 直置き) を原則とする。Feature が判別できれば十分なので、`PlanController` / `MeetingQuotaPlanController` / `CertificationCatalogController` のように **Feature 名 + 役割** で命名すれば namespace で切る必要はない。

| パターン | 例 | 採用可否 | 理由 |
|---|---|---|---|
| **フラット** | `PlanController` / `MeetingQuotaPlanController` / `MeetingQuotaPlanStatusController` | ✅ **原則** | Feature は名前で判別可能、構造を浅く保てる |
| **ロール別 namespace** | `Admin\PlanController` / `Coach\StudentController` / `Student\ChatController` | ❌ **禁止** | リソース固有認可は Policy で分岐すべき。namespace で切るとロール追加・移管時に大規模リネームが必要になり破綻 |
| **Feature 別 namespace** | `MeetingQuota\CheckoutController` / `MeetingQuota\HistoryController` | ⚪ **許容**（迷ったらフラット） | 同一 Feature 内に複数 Controller が生まれる場合の論理グルーピング。ただし `MeetingQuotaCheckoutController` のフラット命名でも代替可能なので、新規実装時はフラットを推奨 |
| **領域別 namespace** | `Auth\OnboardingController` / `Webhooks\StripeWebhookController` | ⚪ **許容** | 画面 Controller ではない外部システム連携の特殊カテゴリ（Fortify Auth 系 / 外部 inbound endpoint）。将来 `Webhooks\GoogleCalendarWebhookController` 等が増える前提でグルーピングする |

#### ❌ 悪い（ロール別 namespace）

```php
// app/Http/Controllers/Admin/UserController.php
namespace App\Http\Controllers\Admin;
// → admin が見る画面だから Admin/ という発想は NG
// → 別ロールが同 Entity の画面を持つときに二重 Controller が生まれる
```

#### ✅ 良い（フラット）

```php
// app/Http/Controllers/UserController.php  ← admin の操作画面
namespace App\Http\Controllers;
// 認可は UserPolicy で admin のみ true に。Coach/Student 用の別画面が必要なら StudentChatController のように Feature ベースで命名
```

#### Feature 別 namespace vs フラット命名の使い分け

同一 Feature 内に複数 Controller (例: `CheckoutController` + `HistoryController`) が生まれて、それぞれの単体名が短すぎて文脈不明になる場合のみ namespace 切りを許容:

| パターン | 判断 |
|---|---|
| `MeetingQuotaCheckoutController` / `MeetingQuotaHistoryController` (フラット) | ✅ 推奨 (短名でも Feature が判別可) |
| `MeetingQuota\CheckoutController` / `MeetingQuota\HistoryController` (namespace) | ⚪ 同程度に許容 (既存実装で採用済なら維持して OK) |

新規 Feature 実装時はフラットを選ぶと迷いがない。**既存で namespace を切ってあるものを無理に flat 化する必要はない**（コスト > 価値）。

```php
namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\MockExamSession;
use App\UseCases\Enrollment\IndexAction;
use App\UseCases\Enrollment\ShowAction;
use App\UseCases\Enrollment\StoreAction;
use App\UseCases\Enrollment\UpdateAction;
use App\UseCases\Enrollment\DestroyAction;
use App\UseCases\MockExam\SubmitAction;
use App\Http\Requests\Enrollment\StoreRequest;
use App\Http\Requests\Enrollment\UpdateRequest;
use App\Http\Requests\MockExam\SubmitRequest;

class EnrollmentController extends Controller
{
    public function index(IndexAction $action)
    {
        return view('enrollments.index', ['enrollments' => $action(auth()->user())]);
    }

    public function show(Enrollment $enrollment, ShowAction $action)
    {
        $this->authorize('view', $enrollment);
        return view('enrollments.show', ['enrollment' => $action($enrollment)]);
    }

    public function store(StoreRequest $request, StoreAction $action)
    {
        $enrollment = $action(auth()->user(), $request->validated());
        return redirect()->route('enrollments.show', $enrollment);
    }

    public function update(Enrollment $enrollment, UpdateRequest $request, UpdateAction $action)
    {
        $this->authorize('update', $enrollment);  // ← Policy はここで
        $action($enrollment, $request->validated());
        return redirect()->route('enrollments.show', $enrollment);
    }

    public function destroy(Enrollment $enrollment, DestroyAction $action)
    {
        $this->authorize('delete', $enrollment);
        $action($enrollment);
        return redirect()->route('enrollments.index');
    }

    // カスタム業務操作も同じ規則: メソッド名 = Action クラス名
    public function submit(MockExamSession $session, SubmitRequest $request, SubmitAction $action)
    {
        $this->authorize('submit', $session);
        $action($session, $request->validated());
        return redirect()->route('mock-exams.show', $session);
    }
}
```

## FormRequest — バリデーション + 認可

- `app/Http/Requests/{Entity}/{Action}Request.php` に配置（例: `StoreRequest.php`）
- `rules()` でバリデーション、`authorize()` で実行可否
- メッセージは `messages()` / `attributes()` で日本語化
- Controller では `$request->validated()` のみ使用

```php
class StoreRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('create', Enrollment::class); }
    public function rules(): array {
        return [
            'certification_id' => ['required', 'ulid', 'exists:certifications,id'],
            'exam_date' => ['required', 'date', 'after:today'],
        ];
    }
}
```

## Route

- `routes/web.php` に画面遷移ルート、`routes/api.php` に **API キー認証エンドポイント**（analytics-export 等、`ApiKeyMiddleware` 経由）
- **Sanctum SPA / 公開 JSON API は LMS 全体で不採用**（`steering/tech.md` 参照）。Blade + Form POST + Redirect で完結する純 Laravel 標準パターンに統一
- リソースルート優先（`Route::resource()`）
- ミドルウェアでロール分岐: `Route::middleware(['auth', 'role:coach'])->group(...)`
- ルート名は `{entity}.{action}` 形式（例: `enrollments.index`）

### `routes/web.php` のコメント方針（必須）

`routes/web.php` は受講生に渡るコードベース。**Feature 固有のコメントを書かない**。Feature の所有は route name の prefix（`admin.meeting-quota-plans.*` / `meeting-quota.*` / `webhooks.stripe` 等）と Controller の namespace で表現する。

#### ❌ 禁止（Feature 固有 / 構築側メタ情報の漏洩）

```php
// [[meeting-quota]] admin: 追加面談 SKU マスタ CRUD + 状態遷移
// [[plan-management]] admin 受講プラン マスタ CRUD + 状態遷移
// [[certification-management]] 受講生カタログ
// REQ-auth-020 / Step 4 で実装 / v3 改修対応 など
```

理由:
- `[[feature-name]]` wikilink は `docs/specs/` への参照。受講生は spec を見れないので `dangling reference` になる（`backend-types-and-docblocks.md`「コードコメントで使わない構築側メタ情報」参照）
- 「いつどこに何を書いたか」は git log / git blame で見るのが筋
- route name の prefix で Feature が判別できるので、コメントによる Feature 表示は冗長

#### ✅ 良い（業務ドメイン名でのグルーピング、構築側用語を含めない）

セクション区切りは 2 段階で書く:
1. **ロールグループの見出し** (1 行) — admin / 受講生 / 共通 / Webhook の所属を明示
2. **業務名のグルーピングコメント** (1 行) — 「ユーザー管理」「プラン管理」「資格マスタ管理」など、受講生が読んでも自然に理解できる **業務ドメイン語彙** で Feature ブロックの先頭に配置

```php
Route::get('/onboarding/{invitation}', ...)->name('onboarding.show');

// ============================================================
// 認証後の受講生・コーチ・管理者共通ルート
// ============================================================
Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'placeholders.coming-soon', ['feature' => 'dashboard'])
        ->name('dashboard.index');
    // ...
});

// ============================================================
// admin 専用ルート
// ============================================================
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // ユーザー管理
    Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->withTrashed()->name('admin.users.show');
    // ...

    // 招待管理
    Route::post('invitations', [InvitationController::class, 'store'])->name('admin.invitations.store');
    // ...

    // プラン管理(受講プラン マスタ + 状態遷移)
    Route::resource('plans', PlanController::class)->parameters(['plans' => 'plan'])->names('admin.plans');
    Route::post('plans/{plan}/publish', [PlanStatusController::class, 'publish'])->name('admin.plans.publish');
    // ...

    // 追加面談プラン管理(SKU マスタ + 状態遷移)
    Route::resource('meeting-quota-plans', MeetingQuotaPlanController::class)
        ->parameters(['meeting-quota-plans' => 'plan'])->names('admin.meeting-quota-plans');
    // ...
});

// ============================================================
// Webhook(認証なし、署名検証 + CSRF 除外)
// ============================================================
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('stripe.signature')
    ->name('webhooks.stripe');
```

許容するコメント:
- **ロールグループの見出し**（`============` 罫線 + 1 行のグループ説明）
- **業務名のグルーピング**（「ユーザー管理」「招待管理」「プラン管理」「追加面談プラン管理」「資格マスタ管理」「カテゴリ管理」「教材管理」「修了証配信」等）
- 慣習から外れる特殊措置（例: `signed` middleware を Controller 内で個別検証する理由）の Why コメント

業務名コメントの判断基準:
- ✅ LMS 業務ドメイン語彙: 受講生 / コーチ / 管理者 / 修了証 / 面談 / 招待 / プラン / 教材 / 資格 / 模試 / 質問 等。受講生が読んでも自然に理解できる用語(`backend-types-and-docblocks.md`「LMS 業務用語は OK」と整合)
- ❌ 構築側メタ用語: `[[feature-name]]` wikilink / `Step N` / `v3 改修` / `Phase X` / `REQ-xxx-yyy` / `Wave 0b`

それ以外の細目(個別 CRUD route の説明 / 「Part の更新は PATCH」みたいな自明説明)は route definition そのものから読めるので書かない。

#### 既存コードの違反整理

過去に書かれた `// [[xxx]]` wikilink コメントや構築側用語コメント(`Step N` / `v3 改修` 等)は見つけ次第削除する。業務名コメントは保持。

## Resource — 公開API用レスポンス整形

- `app/Http/Resources/{Entity}Resource.php`
- **API キー認証エンドポイント**（[[analytics-export]] 等）でのみ使う
- Blade では Model 直接渡しで OK
- **Web Ajax の JSON 返却は inline 配列**（`response()->json([...])`）で十分、Resource は不要
- Eager Loading 前提（N+1 注意）

## Middleware

- ロール存在確認のみ: `EnsureUserRole`（例: `role:coach` で `auth()->user()->role === 'coach'` を確認）
- リソース固有認可は Policy 側で実装（Middleware に詰め込まない）

## View Composer — Blade 全体共通変数の注入

サイドバーバッジ / TopBar 通知件数 / 共通フッター情報 のように **複数 Blade で共有する変数** を毎回 Controller で渡すのは冗長。View Composer で集中注入する。

### 配置と命名

- 配置: `app/View/Composers/{Feature}{Element}Composer.php`
- 命名: `{Feature}{Element}Composer`（例: `SidebarBadgeComposer` / `TopBarNotificationComposer`）
- `final class` 採用、`compose(View $view): void` を実装

### 登録

`AppServiceProvider::boot()` で `View::composer()` 登録:

```php
use App\View\Composers\SidebarBadgeComposer;
use Illuminate\Support\Facades\View;

public function boot(): void
{
    View::composer('layouts._partials.sidebar-*', SidebarBadgeComposer::class);
}
```

ワイルドカード（`*`）でグルーピング可能。`layouts._partials.sidebar-student` / `sidebar-coach` / `sidebar-admin` の 3 ロール別パーシャルに 1 行で適用。

### テンプレート

```php
namespace App\View\Composers;

use App\Services\ChatUnreadCountService;
use Illuminate\View\View;

final class SidebarBadgeComposer
{
    public function __construct(
        private readonly ChatUnreadCountService $chatUnreadCount,
    ) {}

    public function compose(View $view): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $view->with('sidebarBadges', [
            'chat_unread' => $this->chatUnreadCount->forUser($user),
            // 他のバッジ ...
        ]);
    }
}
```

### 注意事項

- **N+1 リスク**: Composer は対象 View が描画されるたびに呼ばれる。複数 Blade で同じバッジを表示する場合、リクエスト 1 回あたり複数回コンポーズされる可能性がある。重い集計は `Cache::remember()` で 1 分キャッシュ等の対策を検討
- **認証前 View では呼ばない**: ログイン画面等の認証前 View では `auth()->user()` が null になる。Composer のロジックで早期 return する
- **テスト**: View Composer 単体テストは不要、Composer が注入する Service の Unit テストで十分
