<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AdminAnnouncement\StoreRequest;
use App\Models\AdminAnnouncement;
use App\UseCases\AdminAnnouncement\IndexAction;
use App\UseCases\AdminAnnouncement\ShowAction;
use App\UseCases\AdminAnnouncement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 管理者お知らせの一覧 / 作成フォーム / 配信 / 詳細閲覧を提供する Controller。
 *
 * 配信は一度きり (再配信 / 編集 / 取消は提供しない)。配信履歴は index で時系列降順で閲覧する。
 */
class AdminAnnouncementController extends Controller
{
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', AdminAnnouncement::class);

        return view('admin.announcements.index', $action());
    }

    public function create(IndexAction $action): View
    {
        $this->authorize('create', AdminAnnouncement::class);

        return view('admin.announcements.create', [
            'certifications' => \App\Models\Certification::query()->orderBy('name')->get(['id', 'name']),
            'students' => \App\Models\User::query()
                ->where('role', \App\Enums\UserRole::Student)
                ->where('status', \App\Enums\UserStatus::InProgress)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $announcement = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', "お知らせを配信しました ({$announcement->dispatched_count} 件)。");
    }

    public function show(AdminAnnouncement $announcement, ShowAction $action): View
    {
        $this->authorize('view', $announcement);

        return view('admin.announcements.show', $action($announcement));
    }
}
