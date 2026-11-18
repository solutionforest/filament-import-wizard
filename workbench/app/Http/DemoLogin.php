<?php

namespace Workbench\App\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\User;

/**
 * Demo only: signs the seeded demo user in on every request so the workbench
 * opens straight on the import page. Never ship this in a real app.
 */
class DemoLogin
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        auth()->guard('web')->setUser(User::query()->firstOrFail());

        return $next($request);
    }
}
