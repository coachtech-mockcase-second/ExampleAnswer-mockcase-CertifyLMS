<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\AiChat\AiChatRateLimitExceededException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // AI 相談 (Gemini) の日次送信上限。.env の AI_CHAT_DAILY_MESSAGE_LIMIT で上書き可能。
        // Gemini 側の RPM (per-minute) は Gemini API 側で 429 として弾かれ、こちらは日次のみ制御する。
        RateLimiter::for('ai-chat', function (Request $request) {
            $userId = $request->user()?->id ?: $request->ip();
            $limit = (int) config('ai-chat.daily_message_limit', 50);

            return Limit::perDay($limit)
                ->by((string) $userId)
                ->response(function () {
                    throw new AiChatRateLimitExceededException;
                });
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
