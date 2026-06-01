<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * ドメイン例外(`backend-exceptions.md` の HttpException 継承クラス群)のうち、
     * ブラウザ(HTML)経由で発火したものを「直前ページへ戻し + Flash error 表示」に変換するステータスコード。
     *
     * 一覧 / 詳細画面で削除・状態遷移ボタンを押下した際に Whoops や素のエラーページではなく、
     * 同じ画面に戻って `<x-flash />` で日本語の理由メッセージを表示するのが Laravel 慣習。
     *
     * JSON 経由(`$request->expectsJson()` true)はデフォルト挙動を維持し、status code + JSON body を返す
     * (テストでは deleteJson / postJson 等を使うと自動的に JSON 期待になる)。
     *
     * 403 (`AccessDeniedHttpException`) は対象外: Policy 拒否は Laravel デフォルトの 403 エラーページに任せる。
     * 「他ロールが admin 専用画面にアクセス → 403 ページ」がセキュリティ的にもユーザー体験的にも自然(redirect だと
     * 「なぜ戻されたか」が伝わりにくい)。403 で redirect したい個別ドメイン例外は、例外クラス側に `render()` メソッドを
     * 生やして個別対応する。
     *
     * @var array<int, int>
     */
    private const REDIRECT_BACK_STATUSES = [
        409,  // ConflictHttpException(状態遷移違反 / 削除不可 / 残数不足等)
        422,  // UnprocessableEntityHttpException(ドメイン規則による拒否、FormRequest バリデーションは ValidationException 経路で別途処理)
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // ブラウザ HTML 経由のドメイン例外を Flash error redirect に変換する。
        // JSON 経由は何も返さない(null を返してデフォルト挙動 = status code + JSON body)。
        $this->renderable(function (HttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            if (! in_array($e->getStatusCode(), self::REDIRECT_BACK_STATUSES, true)) {
                return null;
            }

            return redirect()->back()->with('error', $e->getMessage());
        });

        // 運用エクスポート API (`/api/*`) のレート制限超過は常に JSON で返す。
        $this->renderable(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $this->apiJsonError(
                message: 'リクエスト過多です。しばらく時間を空けて再試行してください。',
                errorCode: 'RATE_LIMIT_EXCEEDED',
                status: 429,
                headers: $e->getHeaders(),
            );
        });

        // 運用エクスポート API の未定義パス / 未許可 method を JSON で返す。
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $this->apiJsonError(
                message: '指定されたリソースが見つかりません。',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $this->apiJsonError(
                message: 'このエンドポイントでは許可されていない HTTP メソッドです。',
                errorCode: 'METHOD_NOT_ALLOWED',
                status: 405,
                headers: $e->getHeaders(),
            );
        });
    }

    /**
     * 運用エクスポート API 共通のエラー JSON 整形。
     *
     * @param array<string, string> $headers
     */
    private function apiJsonError(string $message, string $errorCode, int $status, array $headers = []): JsonResponse
    {
        $response = response()->json([
            'message' => $message,
            'error_code' => $errorCode,
            'status' => $status,
        ], $status);

        $response->header('Content-Type', 'application/json; charset=UTF-8');

        foreach ($headers as $key => $value) {
            $response->header($key, (string) $value);
        }

        return $response;
    }
}
