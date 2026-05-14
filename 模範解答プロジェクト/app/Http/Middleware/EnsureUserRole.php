<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * 使用例: Route::middleware(['auth', 'role:admin'])->group(...)
     *        Route::middleware(['auth', 'role:admin,coach'])->group(...)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
