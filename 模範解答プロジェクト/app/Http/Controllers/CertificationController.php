<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\Certification\IndexRequest;
use App\Http\Requests\Certification\StoreRequest;
use App\Http\Requests\Certification\UpdateRequest;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use App\UseCases\Certification\ArchiveAction;
use App\UseCases\Certification\DestroyAction;
use App\UseCases\Certification\IndexAction;
use App\UseCases\Certification\PublishAction;
use App\UseCases\Certification\ShowAction;
use App\UseCases\Certification\StoreAction;
use App\UseCases\Certification\UnpublishAction;
use App\UseCases\Certification\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin 用の資格マスタ管理画面 Controller。CRUD と公開状態遷移（publish / unpublish / archive）を提供する。
 */
class CertificationController extends Controller
{
    /**
     * 資格マスタを名前キーワード・公開ステータス・カテゴリ・難易度で絞り込んで一覧表示する。
     */
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $certifications = $action(
            viewer: $request->user(),
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status']) ? CertificationStatus::from($validated['status']) : null,
            categoryId: $validated['category_id'] ?? null,
            difficulty: isset($validated['difficulty']) ? CertificationDifficulty::from($validated['difficulty']) : null,
        );

        return view('certification.management.index', [
            'certifications' => $certifications,
            'categories' => CertificationCategory::ordered()->get(),
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
            'categoryId' => $validated['category_id'] ?? '',
            'difficulty' => $validated['difficulty'] ?? '',
        ]);
    }

    /**
     * 資格マスタの詳細を、担当コーチ選択候補とあわせて表示する。
     */
    public function show(Certification $certification, ShowAction $action): View
    {
        $this->authorize('view', $certification);

        $coachCandidates = User::query()
            ->where('role', UserRole::Coach->value)
            ->orderBy('name')
            ->get();

        return view('certification.management.show', [
            'certification' => $action($certification),
            'coachCandidates' => $coachCandidates,
        ]);
    }

    /**
     * 資格マスタの新規作成フォームを表示する。
     */
    public function create(): View
    {
        $this->authorize('create', Certification::class);

        return view('certification.management.create', [
            'categories' => CertificationCategory::ordered()->get(),
        ]);
    }

    /**
     * 資格マスタを新規作成し、作成した詳細画面へリダイレクトする。
     */
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $certification = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタを作成しました。');
    }

    /**
     * 資格マスタの編集フォームを表示する。
     */
    public function edit(Certification $certification): View
    {
        $this->authorize('update', $certification);

        return view('certification.management.edit', [
            'certification' => $certification,
            'categories' => CertificationCategory::ordered()->get(),
        ]);
    }

    /**
     * 資格マスタの内容を更新し、詳細画面へリダイレクトする。
     */
    public function update(Certification $certification, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($certification, $request->user(), $request->validated());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタを更新しました。');
    }

    /**
     * 資格マスタを削除し、一覧画面へリダイレクトする。
     */
    public function destroy(Certification $certification, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $certification);

        $action($certification);

        return redirect()
            ->route('admin.certifications.index')
            ->with('success', '資格マスタを削除しました。');
    }

    /**
     * 資格を公開状態に切り替え、詳細画面へリダイレクトする。
     */
    public function publish(Certification $certification, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $certification);

        $action($certification, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタを公開しました。');
    }

    /**
     * 資格を非公開状態に切り替え、詳細画面へリダイレクトする。
     */
    public function unpublish(Certification $certification, UnpublishAction $action): RedirectResponse
    {
        $this->authorize('unpublish', $certification);

        $action($certification, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタの公開を停止しました。');
    }

    /**
     * 資格をアーカイブ状態に切り替え、詳細画面へリダイレクトする。
     */
    public function archive(Certification $certification, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $certification);

        $action($certification, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタをアーカイブしました。');
    }
}
