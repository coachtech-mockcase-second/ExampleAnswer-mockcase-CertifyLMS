<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ContentSearch\SearchRequest;
use App\Models\Certification;
use App\UseCases\ContentSearch\SearchAction;
use Illuminate\View\View;

/**
 * 受講生向け教材内 Section 全文検索 Controller。
 * keyword と certification_id を受け取り、登録資格内の Published Section のみを返す。
 */
class ContentSearchController extends Controller
{
    public function search(SearchRequest $request, SearchAction $action): View
    {
        $validated = $request->validated();

        $result = $action(
            $request->user(),
            $validated['certification_id'],
            $validated['keyword'] ?? null,
        );

        $certification = Certification::find($validated['certification_id']);

        return view('content.search', [
            'certification' => $certification,
            'paginator' => $result['paginator'],
            'snippets' => $result['snippets'],
            'keyword' => $validated['keyword'] ?? '',
        ]);
    }
}
