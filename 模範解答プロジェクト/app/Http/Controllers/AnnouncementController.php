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
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcement.management.index', $action());
    }

    public function create(IndexAction $action): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcement.management.create', [
            'certifications' => Certification::query()->orderBy('name')->get(['id', 'name']),
            'students' => User::query()
                ->where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
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

    public function show(Announcement $announcement, ShowAction $action): View
    {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', $action($announcement));
    }
}
