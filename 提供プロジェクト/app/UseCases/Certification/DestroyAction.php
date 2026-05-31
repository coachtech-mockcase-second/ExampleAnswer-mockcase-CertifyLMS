<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationNotDeletableException;
use App\Models\Certification;
use Illuminate\Support\Facades\DB;

/**
 * 資格マスタを SoftDelete するユースケース。下書き状態の資格のみ削除可能。
 */
final class DestroyAction
{
    /**
     * @throws CertificationNotDeletableException 下書き状態以外の資格は削除不可
     */
    public function __invoke(Certification $certification): void
    {
        if ($certification->status !== CertificationStatus::Draft) {
            throw new CertificationNotDeletableException;
        }

        DB::transaction(fn () => $certification->delete());
    }
}
