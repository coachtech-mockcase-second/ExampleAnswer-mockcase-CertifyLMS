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
use App\UseCases\Certification\UnarchiveAction;
use App\UseCases\Certification\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CertificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $certifications = $action(
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status']) ? CertificationStatus::from($validated['status']) : null,
            categoryId: $validated['category_id'] ?? null,
            difficulty: isset($validated['difficulty']) ? CertificationDifficulty::from($validated['difficulty']) : null,
        );

        return view('admin.certifications.index', [
            'certifications' => $certifications,
            'categories' => CertificationCategory::ordered()->get(),
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
            'categoryId' => $validated['category_id'] ?? '',
            'difficulty' => $validated['difficulty'] ?? '',
        ]);
    }

    public function show(Certification $certification, ShowAction $action): View
    {
        $this->authorize('view', $certification);

        $coachCandidates = User::query()
            ->where('role', UserRole::Coach->value)
            ->orderBy('name')
            ->get();

        return view('admin.certifications.show', [
            'certification' => $action($certification),
            'coachCandidates' => $coachCandidates,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Certification::class);

        return view('admin.certifications.create', [
            'categories' => CertificationCategory::ordered()->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $certification = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタを作成しました。');
    }

    public function edit(Certification $certification): View
    {
        $this->authorize('update', $certification);

        return view('admin.certifications.edit', [
            'certification' => $certification,
            'categories' => CertificationCategory::ordered()->get(),
        ]);
    }

    public function update(Certification $certification, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($certification, $request->user(), $request->validated());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタを更新しました。');
    }

    public function destroy(Certification $certification, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $certification);

        $action($certification);

        return redirect()
            ->route('admin.certifications.index')
            ->with('success', '資格マスタを削除しました。');
    }

    public function publish(Certification $certification, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $certification);

        $action($certification, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタを公開しました。');
    }

    public function archive(Certification $certification, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $certification);

        $action($certification, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタをアーカイブしました。');
    }

    public function unarchive(Certification $certification, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $certification);

        $action($certification, request()->user());

        return redirect()
            ->route('admin.certifications.show', $certification)
            ->with('success', '資格マスタを下書きへ戻しました。');
    }
}
