<?php

namespace XWMS\Package\Providers;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use XWMS\Package\Helpers\LocaleHelper;

class LocaleFromUrl
{
    public function handle($request, Closure $next)
    {
        $supported = config('locales.supported', ['en']);
        $default = config('locales.default', 'en');
        $aliases = config('locales.aliases', []);

        $segment = $request->segment(1);
        $sessionLocale = Session::get('locale');
        $localeActive = Session::get('locale_active', false);

        $segment = $aliases[$segment] ?? $segment;
        if ($sessionLocale) {
            $sessionLocale = $aliases[$sessionLocale] ?? $sessionLocale;
        }

        $preferred = null;
        if (!$localeActive) {
            $preferred = $request->getPreferredLanguage();
            $preferred = Str::of($preferred)->replace('-', '_')->toString();
            $preferred = $aliases[$preferred] ?? $preferred;
        }

        $refererLocale = null;
        if ($request->headers->get('referer')) {
            $parsed = parse_url($request->headers->get('referer'));
            $path = trim($parsed['path'] ?? '', '/');
            $first = explode('/', $path)[0] ?? null;

            $first = $aliases[$first] ?? $first;
            if (in_array($first, $supported, true)) {
                $refererLocale = $first;
            }
        }

        if (in_array($segment, $supported, true)) {
            $locale = $segment;
        } elseif ($sessionLocale && in_array($sessionLocale, $supported, true)) {
            $locale = $sessionLocale;
        } elseif ($refererLocale) {
            $locale = $refererLocale;
        } elseif (!$localeActive && $preferred) {
            if (in_array($preferred, $supported, true)) {
                $locale = $preferred;
            } else {
                $short = substr($preferred, 0, 2);
                $locale = in_array($short, $supported, true) ? $short : $default;
            }
        } else {
            $locale = $default;
        }

        App::setLocale($locale);
        Session::put('locale', $locale);
        Session::put('locale_active', true);

        if (
            $request->isMethod('get') &&
            !$request->is('api/*') &&
            !$request->is('livewire*') &&
            !$request->expectsJson()
        ) {
            $path = trim($request->path(), '/');
            $localizedUrls = LocaleHelper::getLocalizedUrls();

            if (!in_array($segment, $supported, true)) {
                if ($path === '') {
                    return redirect("/{$locale}");
                }

                if (in_array($path, $localizedUrls, true)) {
                    return redirect("/{$locale}/{$path}");
                }
            }
        }

        return $next($request);
    }
}
