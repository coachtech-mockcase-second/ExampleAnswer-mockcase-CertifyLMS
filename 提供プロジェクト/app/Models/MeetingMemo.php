<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MeetingMemoFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 面談メモ(1 面談 : 1 メモ)を表す Model。
 *
 * 記述者は `meeting.coach` で一意に決まるため author カラムを持たない。
 * `reserved` 段階での事前メモはコーチ内部用、`completed` 後は受講生も閲覧可能。
 *
 * 関連: Meeting(belongsTo)
 */
class MeetingMemo extends Model
{
    /** @use HasFactory<MeetingMemoFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'meeting_id',
        'body',
    ];

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
