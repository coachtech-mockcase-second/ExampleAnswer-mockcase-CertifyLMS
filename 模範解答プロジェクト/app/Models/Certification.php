<?php

namespace App\Models;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certification extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'code',
        'category_id',
        'name',
        'slug',
        'description',
        'difficulty',
        'passing_score',
        'total_questions',
        'exam_duration_minutes',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
        'published_at',
        'archived_at',
    ];

    protected $casts = [
        'status' => CertificationStatus::class,
        'difficulty' => CertificationDifficulty::class,
        'passing_score' => 'integer',
        'total_questions' => 'integer',
        'exam_duration_minutes' => 'integer',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CertificationCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'certification_coach_assignments',
            'certification_id',
            'coach_user_id',
        )
            ->using(CertificationCoachAssignment::class)
            ->withPivot(['id', 'assigned_by_user_id', 'assigned_at'])
            ->withTimestamps();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function questionCategories(): HasMany
    {
        return $this->hasMany(QuestionCategory::class);
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

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        $like = '%'.$keyword.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('code', 'LIKE', $like)
                ->orWhere('name', 'LIKE', $like);
        });
    }
}
