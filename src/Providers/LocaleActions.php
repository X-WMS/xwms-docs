<?php

namespace XWMS\Package\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use XWMS\Package\Helpers\LocaleHelper;

class LocaleActions
{
    public function setLocale(Request $request, string $locale)
    {
        $supported = config('locales.supported', []);
        $aliases = config('locales.aliases', []);
        $default = config('locales.default', 'en');
        $localizedUrls = LocaleHelper::getLocalizedUrls();

        $locale = LocaleHelper::normalizeLocale($locale, $supported, $aliases, $default);

        session(['locale' => $locale, 'locale_active' => true]);
        App::setLocale($locale);

        $segments = LocaleHelper::stripLeadingLocale($request->segments(), $supported);
        $newPath = trim(implode('/', $segments), '/');
        $newUrl = LocaleHelper::buildLocalizedUrl($newPath, $locale, $localizedUrls);

        if ($q = $request->getQueryString()) {
            $newUrl .= '?' . $q;
        }

        return redirect($newUrl);
    }

    public function postLocale(Request $request)
    {
        $supported = config('locales.supported', []);
        $aliases = config('locales.aliases', []);
        $default = config('locales.default', 'en');
        $localizedUrls = LocaleHelper::getLocalizedUrls();

        $inputLocale = $request->input('locale', $default);
        $locale = LocaleHelper::normalizeLocale($inputLocale, $supported, $aliases, $default);

        session(['locale' => $locale, 'locale_active' => true]);
        App::setLocale($locale);

        $referer = $request->headers->get('referer');
        $path = parse_url($referer, PHP_URL_PATH) ?? '';
        $segments = array_filter(explode('/', trim($path, '/')));
        $segments = LocaleHelper::stripLeadingLocale($segments, $supported);

        $newPath = trim(implode('/', $segments), '/');
        $newUrl = LocaleHelper::buildLocalizedUrl($newPath, $locale, $localizedUrls);

        $query = parse_url($referer, PHP_URL_QUERY);
        if ($query) {
            $newUrl .= '?' . $query;
        }

        return redirect($newUrl);
    }
}
