<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EnrollmentNoteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * コーチが Enrollment 単位で受講生の観察を時系列で残すメモ。受講生本人は閲覧不可。
 * coach は自分の作成分のみ編集 / 削除可、admin は越境可。
 */
class EnrollmentNote extends Model
{
    /** @use HasFactory<EnrollmentNoteFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'enrollment_id',
        'coach_user_id',
        'body',
    ];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * 作成者(coach or admin)。foreign key 名通り coach_user_id を参照する。
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }
}
