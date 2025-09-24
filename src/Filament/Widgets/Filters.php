<?php

namespace XWMS\Package\Filament\Widgets;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Filters
{
    public static function resolveDateFilter(array $filters): array
    {
        $defaultStart = now()->subMonth()->startOfDay();
        $defaultEnd = now()->endOfDay();

        $start = Carbon::parse($filters['startDate'] ?? $defaultStart);
        $end = Carbon::parse($filters['endDate'] ?? $defaultEnd);
        $manualInterval = $filters['interval'] ?? 'auto';

        $diffInHours = $start->diffInHours($end);
        $interval = match (true) {
            $manualInterval !== 'auto' => $manualInterval,
            $diffInHours <= 24 => 'hour',
            $diffInHours <= 24 * 32 => 'day',
            $diffInHours <= 24 * 7 * 26 => 'week',
            $diffInHours <= 24 * 30 * 36 => 'month',
            default => 'year',
        };

        $dateFormat = match ($interval) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%x-W%v',
            'month' => '%Y-%m',
            'year' => '%Y',
        };

        return compact('start', 'end', 'dateFormat', 'interval');
    }

    public static function normalizeLabel(string $path, int $depth = 2): string
    {
        $label = trim($path, '/') ?: '/';
        if ($label === '/') return 'home';
        $words = preg_split('/[\s\/_-]+/', $label);
        $filtered = array_filter($words, fn($w) => !is_numeric($w));
        $group = array_slice($filtered, 0, $depth);
        return strtolower(implode(' ', $group));
    }

    public static function getColorForLabel(string $label): object
    {
        $key = strtolower($label);

        if (isset(self::$manualColors[$key])) {
            $rgba = self::$manualColors[$key];
            // Parse rgba or hex to RGB + opacity closure:
            return new class($rgba) {
                public function __construct(protected string $rgba) {}
                public function get(float $opacity = 1.0): string
                {
                    if (str_starts_with($this->rgba, 'rgba')) {
                        // Verander opacity in rgba string (regex)
                        return preg_replace('/rgba\((\d+),\s*(\d+),\s*(\d+),\s*[\d\.]+\)/', "rgba($1, $2, $3, $opacity)", $this->rgba);
                    }
                    // Hex -> rgba converter
                    if (preg_match('/^#?([a-f0-9]{6})$/i', $this->rgba, $m)) {
                        $hex = $m[1];
                        $r = hexdec(substr($hex, 0, 2));
                        $g = hexdec(substr($hex, 2, 2));
                        $b = hexdec(substr($hex, 4, 2));
                        return "rgba($r, $g, $b, $opacity)";
                    }
                    // fallback, return raw kleur met opacity als 1 (geen opacity support)
                    return $this->rgba;
                }
            };
        }

        // fallback: originele hash-based kleur
        $hash = crc32($label);
        $r = ($hash & 0xFF0000) >> 16;
        $g = ($hash & 0x00FF00) >> 8;
        $b = $hash & 0x0000FF;

        return new class($r, $g, $b)
        {
            public function __construct(
                protected int $r,
                protected int $g,
                protected int $b,
            ) {}

            public function get(float $opacity = 1.0): string
            {
                return "rgba({$this->r}, {$this->g}, {$this->b}, {$opacity})";
            }
        };
    }

    protected static array $manualColors = [];
    public static function setColorForLabel(string $label, string $color): void
    {
        self::$manualColors[strtolower($label)] = $color;
    }

    public static function defaultGetData(
        $class,
        $query,
        string $key,
        ?callable $normalizeLabelCallback = null,
        ?callable $modifyQueryCallback = null,
        ?int $limit = 20
    ): array {
        ['start' => $start, 'end' => $end, 'dateFormat' => $dateFormat] = self::resolveDateFilter($class->filterFormData ?? []);

        // Pas query aan als callback gegeven is (bv. extra select of where)
        if ($modifyQueryCallback) {
            $modifyQueryCallback($query, $dateFormat, $start, $end);
        } else {
            $query->selectRaw("DATE_FORMAT(created_at, ?) as period, $key, COUNT(*) as total", [$dateFormat])
                ->whereBetween('created_at', [$start, $end]);
        }

        $data = $query
            ->groupBy('period', $key)
            ->orderBy('period');

        if ($limit !== null) {
            $query->limit($limit);
        }
        
        $data = $query->get();

        $categories = $data->pluck('period')->unique()->values()->all();
        $grouped = [];

        foreach ($data as $entry) {
            $value = $entry->$key;

            // Normaliseer label (default of via callback)
            if ($normalizeLabelCallback) {
                $label = $normalizeLabelCallback($value);
            } else {
                // Default normalisatie: url-path cleanup
                $label = self::normalizeLabel(parse_url($value, PHP_URL_PATH) ?? $value);
            }

            $period = $entry->period;
            $count = $entry->total;

            if (!isset($grouped[$label])) {
                $grouped[$label] = array_fill_keys($categories, 0);
            }

            $grouped[$label][$period] += $count;
        }

        $datasets = [];

        foreach ($grouped as $label => $valuesPerPeriod) {
            $color = self::getColorForLabel($label);
            $datasets[] = [
                'label' => $label,
                'data' => array_values($valuesPerPeriod),
                'borderColor' => $color->get(),
                'backgroundColor' => $color->get(0.3),
                'fill' => false,
            ];
        }

        return [
            'labels' => $categories,
            'datasets' => $datasets,
        ];
    }

    public static function defaultDonutData(array $data, array $labels, $heading, $colors = ['#6366f1', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'])
    {
        return [
            'chart' => [
                'type' => 'donut',
                'toolbar' => ['show' => false],
            ],
            'series' => $data,
            'labels' => $labels,
            'title' => [
                'text' => $heading,
                'align' => 'center',
                'style' => [
                    'fontSize' => '16px',
                    'fontWeight' => 'bold',
                    'color' => '#e2e8f0',
                    'fontFamily' => 'Inter, sans-serif',
                ],
            ],
            'legend' => [
                'position' => 'bottom',
                'labels' => [
                    'colors' => '#94a3b8',
                    'useSeriesColors' => false,
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
                'style' => [
                    'fontSize' => '12px',
                    'fontWeight' => 'bold',
                    'colors' => ['#ffffff'],
                ],
            ],
            'tooltip' => [
                'enabled' => true,
                'theme' => 'dark',
            ],
            'colors' => $colors,
        ];
    }

    public static function getLabeledCounts(
        array $filterFormData,
        string $table,
        string $labelColumn,
        string $chartTitle = '',
        ?array $colors = null,
        ?string $countExpression = 'COUNT(DISTINCT session_id)',
        int $limit = 5
    ): array {
        ['start' => $start, 'end' => $end] = self::resolveDateFilter($filterFormData);

        $results = DB::table($table)
            ->select($labelColumn, DB::raw("$countExpression as count"))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy($labelColumn)
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        $labels = $results->pluck($labelColumn)->toArray();
        $data = $results->pluck('count')->toArray();

        return self::defaultDonutData($data, $labels, $chartTitle, $colors);
    }
}