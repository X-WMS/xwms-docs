<?php

namespace XWMS\Package\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocaleHelper
{
    public static function normalizeLocale(string $locale, array $supported, array $aliases, string $default): string
    {
        $locale = $aliases[$locale] ?? $locale;

        return in_array($locale, $supported, true) ? $locale : $default;
    }

    public static function getPreferredLocale(Request $request, array $supported, array $aliases, string $default): string
    {
        $preferred = $request->getPreferredLanguage();
        $preferred = Str::of($preferred)->replace('-', '_')->toString();
        $preferred = $aliases[$preferred] ?? $preferred;

        if (in_array($preferred, $supported, true)) {
            return $preferred;
        }

        $short = substr($preferred, 0, 2);

        return in_array($short, $supported, true) ? $short : $default;
    }

    public static function getLocalizedUrls(): array
    {
        return collect(config('locales.localized_urls', []))
            ->map(fn ($item) => is_array($item) ? trim($item['url'] ?? '', '/') : trim($item, '/'))
            ->filter()
            ->all();
    }

    public static function stripLeadingLocale(array $segments, array $supported): array
    {
        if (!empty($segments) && in_array($segments[0], $supported, true)) {
            array_shift($segments);
        }

        return $segments;
    }

    public static function buildLocalizedUrl(string $path, string $locale, array $localizedUrls): string
    {
        $path = trim($path, '/');
        $isLocalized = $path === '' || in_array($path, $localizedUrls, true);

        return $isLocalized
            ? url($locale . ($path ? '/' . $path : ''))
            : url($path);
    }
}
