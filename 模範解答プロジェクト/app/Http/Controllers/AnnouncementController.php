<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Announcement\IndexAction;
use App\UseCases\Announcement\ShowAction;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 管理者お知らせの一覧 / 作成フォーム / 配信 / 詳細閲覧を提供する Controller。
 *
 * 配信は一度きり (再配信 / 編集 / 取消は提供しない)。配信履歴は index で時系列降順で閲覧する。
 */
class AnnouncementController extends Controller
{
    /**
     * 配信済みお知らせの一覧 (配信履歴) を時系列降順で表示する。
     */
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcement.management.index', $action());
    }

    /**
     * お知らせ作成フォームを表示する。配信対象の選択肢として公開中の資格と受講中の受講生を渡す。
     */
    public function create(IndexAction $action): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcement.management.create', [
            'certifications' => Certification::query()->published()->orderBy('name')->get(['id', 'name']),
            'students' => User::query()
                ->where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * お知らせを作成して対象受講生へ配信し、配信件数付きフラッシュとともに詳細へリダイレクトする。
     */
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $announcement = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', "お知らせを配信しました ({$announcement->dispatched_count} 件)。");
    }

    /**
     * お知らせ配信の詳細 (配信内容・配信対象・配信件数) を表示する。
     */
    public function show(Announcement $announcement, ShowAction $action): View
    {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', $action($announcement));
    }
}
