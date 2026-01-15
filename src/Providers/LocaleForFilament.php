<?php

namespace XWMS\Package\Providers;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use XWMS\Package\Helpers\LocaleHelper;

class LocaleForFilament
{
    public function handle($request, Closure $next)
    {
        $supported = config('locales.supported', ['en']);
        $aliases = config('locales.aliases', []);
        $default = config('locales.default', 'en');

        $sessionLocale = Session::get('locale');
        $locale = null;

        if ($sessionLocale) {
            $locale = $aliases[$sessionLocale] ?? $sessionLocale;
        } else {
            $locale = LocaleHelper::getPreferredLocale($request, $supported, $aliases, $default);
        }

        if (!in_array($locale, $supported, true)) {
            $locale = $default;
        }

        App::setLocale($locale);
        Session::put('locale', $locale);
        Session::put('locale_active', true);

        return $next($request);
    }
}
