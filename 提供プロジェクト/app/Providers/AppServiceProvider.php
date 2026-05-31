<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\AiChat\AiChatNotConfiguredException;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\Repositories\GeminiLlmRepository;
use App\View\Composers\EnrollmentSwitcherComposer;
use App\View\Composers\NotificationBadgeComposer;
use App\View\Composers\SectionPageMetaComposer;
use App\View\Composers\SidebarBadgeComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient((string) config('services.stripe.secret'));
        });

        $this->app->bind(LlmRepositoryInterface::class, function () {
            $driver = (string) config('ai-chat.driver', 'gemini');

            return match ($driver) {
                'gemini' => $this->makeGeminiRepository(),
                default => throw new \RuntimeException("Unsupported LLM driver: {$driver}"),
            };
        });
    }

    public function boot(): void
    {
        View::composer('layouts._partials.sidebar-*', SidebarBadgeComposer::class);
        View::composer('layouts._partials.topbar', NotificationBadgeComposer::class);
        View::composer('components.enrollment-switcher', EnrollmentSwitcherComposer::class);
        View::composer('learning.sections.show', SectionPageMetaComposer::class);
    }

    /**
     * Gemini Repository を生成する。API キー未設定時は AI 相談機能が呼ばれた瞬間に
     * AiChatNotConfiguredException が throw されるよう、Repository 解決時にチェックする。
     */
    private function makeGeminiRepository(): GeminiLlmRepository
    {
        $apiKey = (string) config('ai-chat.gemini.api_key', '');
        if ($apiKey === '') {
            throw new AiChatNotConfiguredException;
        }

        return new GeminiLlmRepository(
            endpoint: (string) config('ai-chat.gemini.endpoint'),
            apiKey: $apiKey,
            defaultModel: (string) config('ai-chat.gemini.model'),
        );
    }
}
