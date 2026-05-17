<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;

/**
 * 修了証の証書番号（`CT-{YYYYMM}-{NNNNN}` 形式）を採番する Service。
 * 当月内の最大連番に FOR UPDATE ロックを掛け、競合下でも UNIQUE 違反しないように同期する。
 * トランザクション境界は呼出側（`Certificate\IssueAction` の `DB::transaction`）に委譲する。
 */
final class CertificateSerialNumberService
{
    public function generate(): string
    {
        $yyyymm = now()->format('Ym');
        $prefix = "CT-{$yyyymm}-";

        $maxNo = Certificate::query()
            ->where('serial_no', 'LIKE', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('serial_no')
            ->value('serial_no');

        $next = $maxNo === null
            ? 1
            : ((int) substr($maxNo, -5)) + 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
