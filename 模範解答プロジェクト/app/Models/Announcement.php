<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 管理者から受講生集合へ配信するお知らせの本体を表す Model。
 *
 * target_type で配信対象集合を切り替える: AllStudents / Certification / User。
 * 配信は不可逆 (再配信 / 編集 / 取消は提供しない)。配信後の参照系のみ index / show で提供。
 *
 * 関連: createdBy(配信した admin) / targetCertification(target_type=Certification 時) / targetUser(target_type=User 時)
 * 主要 Action: \App\UseCases\Announcement\StoreAction (本体作成 + 各 User へ通知発火)
 */
class Announcement extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'created_by_user_id',
        'title',
        'body',
        'target_type',
        'target_certification_id',
        'target_user_id',
        'dispatched_count',
        'dispatched_at',
    ];

    protected $casts = [
        'target_type' => AnnouncementTargetType::class,
        'dispatched_count' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function targetCertification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'target_certification_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id')->withTrashed();
    }
}
