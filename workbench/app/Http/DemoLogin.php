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
        // Demo only: `?lang=zh_HK` switches the (single, shared) demo language and sticks.
        $lang = $request->query('lang');

        if (is_string($lang) && in_array($lang, ['en', 'zh_CN', 'zh_TW', 'zh_HK'], true)) {
            cache()->forever('demo_locale', $lang);
        }

        $locale = cache()->get('demo_locale', 'en');
        app()->setLocale(is_string($locale) ? $locale : 'en');

        auth()->guard('web')->setUser(User::query()->firstOrFail());

        return $next($request);
    }
}
