<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Avatar\StoreRequest;
use App\UseCases\Avatar\DestroyAction;
use App\UseCases\Avatar\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 本人のアバター画像アップロード / 削除のエントリポイント。
 *
 * - `store`: multipart で受け取った `avatar` ファイルを `Avatar\StoreAction` に委譲して保存。
 * - `destroy`: 既存ファイルを `Avatar\DestroyAction` に委譲して削除し、`users.avatar_url=NULL` に戻す。
 *
 * いずれもプロフィールタブへリダイレクト + Flash で結果通知する。
 */
class AvatarController extends Controller
{
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $file = $request->file('avatar');
        // FormRequest 側で required + file 検証済、ここに到達した時点で UploadedFile が確定する
        if ($file === null || is_array($file)) {
            return back()->with('error', 'アバター画像の解析に失敗しました。');
        }

        $action($request->user(), $file);

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'profile'])
            ->with('success', 'アバター画像を更新しました。');
    }

    public function destroy(Request $request, DestroyAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'profile'])
            ->with('success', 'アバター画像を削除しました。');
    }
}
