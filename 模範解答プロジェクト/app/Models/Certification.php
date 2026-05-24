<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use Database\Factories\CertificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 資格マスタを表す Model。受講生カタログ・admin マスタ管理・修了証発行・教材階層の親として参照される。
 *
 * 関連: CertificationCategory / Part(教材) / MockExam(模試) / Enrollment(受講登録) / Certificate(修了証) / User(担当コーチ via certification_coach_assignments)
 * scope: published / assignedTo(User) / forUser(User)(admin = 全件 / coach = 担当のみ) / keyword(?string)(資格名のみ部分一致)
 */
class Certification extends Model
{
    /** @use HasFactory<CertificationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'category_id',
        'difficulty',
        'description',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
        'published_at',
        'archived_at',
    ];

    protected $casts = [
        'status' => CertificationStatus::class,
        'difficulty' => CertificationDifficulty::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CertificationCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CertificationCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * 担当コーチ一覧。active 行のみを返す(unassigned_at IS NULL で現役判定)。
     *
     * @return BelongsToMany<User, $this>
     */
    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'certification_coach_assignments',
            'certification_id',
            'user_id',
        )
            ->using(CertificationCoachAssignment::class)
            ->withPivot(['id', 'assigned_by_user_id', 'assigned_at', 'unassigned_at'])
            ->withTimestamps()
            ->wherePivot('unassigned_at', null);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<Part, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    /**
     * @return HasMany<QuestionCategory, $this>
     */
    public function questionCategories(): HasMany
    {
        return $this->hasMany(QuestionCategory::class);
    }

    /**
     * @return HasMany<MockExam, $this>
     */
    public function mockExams(): HasMany
    {
        return $this->hasMany(MockExam::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', CertificationStatus::Published->value);
    }

    public function scopeAssignedTo(Builder $query, User $coach): Builder
    {
        return $query->whereHas(
            'coaches',
            fn (Builder $q) => $q->where('users.id', $coach->id),
        );
    }

    /**
     * 操作者ロールに応じて一覧表示行を絞り込む scope。admin は全件、coach は担当資格のみ、その他は空集合。
     * admin / coach 共通の一覧画面 (`admin.certifications.index`) で利用する。
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Coach => $query->assignedTo($user),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        return $query->where('name', 'LIKE', '%'.$keyword.'%');
    }
}
