<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SectionImageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Section 本文(Markdown)に埋め込む教材内画像のメタデータを表す Model。
 *
 * 関連: Section(親)
 * 実ファイルは Storage public driver の `section-images/{ulid}.{ext}` に保存される。
 */
class SectionImage extends Model
{
    /** @use HasFactory<SectionImageFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'section_id',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
