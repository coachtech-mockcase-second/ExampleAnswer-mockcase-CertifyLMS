<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureUserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_when_role_matches(): void
    {
        $admin = User::factory()->admin()->create();
        $request = Request::create('/admin/users');
        $request->setUserResolver(fn () => $admin);

        $middleware = new EnsureUserRole();
        $response = $middleware->handle($request, fn () => response('OK'), 'admin');

        $this->assertSame('OK', $response->getContent());
    }

    public function test_passes_when_one_of_multiple_roles_matches(): void
    {
        $coach = User::factory()->coach()->create();
        $request = Request::create('/admin/users');
        $request->setUserResolver(fn () => $coach);

        $middleware = new EnsureUserRole();
        $response = $middleware->handle($request, fn () => response('OK'), 'admin', 'coach');

        $this->assertSame('OK', $response->getContent());
    }

    public function test_aborts_403_when_role_mismatches(): void
    {
        $student = User::factory()->student()->create();
        $request = Request::create('/admin/users');
        $request->setUserResolver(fn () => $student);

        $middleware = new EnsureUserRole();

        try {
            $middleware->handle($request, fn () => response('OK'), 'admin');
            $this->fail('HttpException should have been thrown');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_aborts_403_when_unauthenticated(): void
    {
        $request = Request::create('/admin/users');
        $request->setUserResolver(fn () => null);

        $middleware = new EnsureUserRole();

        try {
            $middleware->handle($request, fn () => response('OK'), 'admin');
            $this->fail('HttpException should have been thrown');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
