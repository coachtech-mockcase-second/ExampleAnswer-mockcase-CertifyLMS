<?php

declare(strict_types=1);

namespace App\UseCases\Certification;

use App\Enums\CertificationStatus;
use App\Exceptions\Certification\CertificationInvalidTransitionException;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 資格マスタの公開を停止（published → draft）するユースケース。
 * 公開中以外の状態からの呼出は CertificationInvalidTransitionException（409）。
 * `published_at` はリセットしない（過去の初回公開日時を履歴として保持）。
 */
final class UnpublishAction
{
    /**
     * @throws CertificationInvalidTransitionException 公開中以外からの呼出
     */
    public function __invoke(Certification $certification, User $admin): Certification
    {
        if ($certification->status !== CertificationStatus::Published) {
            throw CertificationInvalidTransitionException::forUnpublish();
        }

        return DB::transaction(function () use ($certification, $admin) {
            $certification->update([
                'status' => CertificationStatus::Draft->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $certification->fresh();
        });
    }
}
