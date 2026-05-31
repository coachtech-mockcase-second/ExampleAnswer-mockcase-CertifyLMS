<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingQuotaTransactionType;
use App\Http\Requests\MeetingQuota\HistoryIndexRequest;
use App\Services\MeetingQuotaService;
use Illuminate\View\View;

/**
 * 受講生本人の面談回数履歴を表示する Controller。
 * type フィルタ + paginate で表示し、残面談回数も合わせて表示する。
 */
class MeetingQuotaHistoryController extends Controller
{
    public function index(HistoryIndexRequest $request, MeetingQuotaService $service): View
    {
        $user = $request->user();
        $validated = $request->validated();
        $type = isset($validated['type'])
            ? MeetingQuotaTransactionType::from($validated['type'])
            : null;

        $transactions = $service->history($user, $type);

        return view('meeting-quota.history', [
            'transactions' => $transactions,
            'remaining' => $service->remaining($user),
            'type' => $validated['type'] ?? '',
        ]);
    }
}
