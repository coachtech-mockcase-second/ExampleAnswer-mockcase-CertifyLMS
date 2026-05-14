<?php

namespace Tests\Unit\Services;

use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InvitationTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvitation(): Invitation
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->invited()->create();

        return Invitation::factory()
            ->forUser($user)
            ->pending()
            ->create(['invited_by_user_id' => $admin->id]);
    }

    public function test_generate_url_includes_expires_query(): void
    {
        $invitation = $this->makeInvitation();

        $url = app(InvitationTokenService::class)->generateUrl($invitation);

        $this->assertStringContainsString('expires=', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString($invitation->id, $url);
    }

    public function test_verify_returns_true_for_valid_request(): void
    {
        $invitation = $this->makeInvitation();
        $service = app(InvitationTokenService::class);
        $url = $service->generateUrl($invitation);

        $request = Request::create($url, 'GET');
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));
        $this->app->instance('request', $request);

        $this->assertTrue($service->verify($request, $invitation));
    }

    public function test_verify_returns_false_for_tampered_signature(): void
    {
        $invitation = $this->makeInvitation();
        $service = app(InvitationTokenService::class);
        $url = $service->generateUrl($invitation);
        $tampered = preg_replace('/signature=[^&]+/', 'signature=tampered', $url);

        $request = Request::create($tampered, 'GET');
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $this->assertFalse($service->verify($request, $invitation));
    }
}
