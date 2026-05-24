---
paths:
  - "提供プロジェクト/app/Models/**"
  - "提供プロジェクト/app/Enums/**"
  - "提供プロジェクト/database/migrations/**"
  - "提供プロジェクト/database/factories/**"
  - "模範解答プロジェクト/app/Models/**"
  - "模範解答プロジェクト/app/Enums/**"
  - "模範解答プロジェクト/database/migrations/**"
  - "模範解答プロジェクト/database/factories/**"
---

# Eloquent モデル規約

## 必須事項

- 主キーは **ULID**（`use HasUlids`）。URL 安全 + 時系列ソート可
- `$fillable` 必須（mass assignment 防止）
- `$casts` で型変換明示（datetime / enum / boolean / array）
- リレーションメソッドは複数形 + lower camelCase（`hasMany` / `belongsTo`）
- 再利用クエリは `scope*` メソッドで定義

## SoftDelete の採用判断

**デフォルトは物理削除**。SoftDelete は **明確な要件がある Entity のみ** 採用する(Laravel コミュニティの業界標準、COACHTECH LMS 既存パターンと整合)。

### 採用する判断軸

以下のいずれかを満たす Entity のみ `use SoftDeletes;` を追加する:

1. **誤削除の復旧 UX が業務要件としてある** (管理者画面に「論理削除済を含める」フィルタ + restore 動線がある等)
2. **会計監査・法的保管義務がある** (Payment / 取引履歴等)
3. **退会・キャンセル後も他 Entity から参照される必要がある** (User 退会後の `withTrashed()` 解決等)

### 採用しないケース

- マスタ系で `Draft / Published / Archived` の status 列を持つ Entity (status = Archived で十分)
- 進捗・履歴・累計集計テーブル (削除時の復旧概念がない)
- pivot 表 / 中間テーブル (脱退切替は物理削除で十分)
- ログ系・INSERT only テーブル (そもそも削除しない)

### SoftDelete のリスク認識

- 全クエリに `WHERE deleted_at IS NULL` が自動付与され、テーブル肥大化時に性能劣化
- UNIQUE 制約と `deleted_at` の併用で「ゾンビ行による UNIQUE 違反」のリスク
- Query Builder (`DB::table()`) は `deleted_at` を自動付与しないため、Eloquent との挙動差を生む

## モデルテンプレート

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\EnrollmentStatus;

class Enrollment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id', 'certification_id', 'exam_date', 'status', 'current_term', 'passed_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'passed_at' => 'datetime',
        'status' => EnrollmentStatus::class,
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function certification() { return $this->belongsTo(Certification::class); }
    public function goals() { return $this->hasMany(EnrollmentGoal::class); }

    public function scopeLearning($query) { return $query->where('status', EnrollmentStatus::Learning); }
}
```

SoftDelete 採用する Entity (User / Enrollment / Invitation / Payment 等) はテンプレートに `use Illuminate\Database\Eloquent\SoftDeletes;` import + `SoftDeletes` Trait を追加する。

## マイグレーション規約

- ULID 主キー: `$table->ulid('id')->primary();`
- 外部キー: `$table->foreignUlid('user_id')->constrained()->cascadeOnDelete();`
- `$table->timestamps();` を必ず付ける
- `$table->softDeletes();` は上記「SoftDelete の採用判断」を満たす Entity のみ
- 命名: `create_{table_name}_table` / `add_{column}_to_{table}_table`

## Enum

- 状態は PHP Enum（`backed enum`、string）で表現
- `label()` メソッドで日本語表示名を返す
- 例: `EnrollmentStatus::Learning`, `MockExamSessionStatus::NotStarted`

## ファクトリー

- `database/factories/{Model}Factory.php` に配置
- テスト・シーダー双方で使う前提でリアルな値を返す
- `state()` でバリエーション提供（例: `EnrollmentFactory::new()->passed()`）
