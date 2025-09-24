<?php

namespace XWMS\Package\Helpers;

use Filament\Tables\Columns\TextColumn;
use Carbon\Carbon;

class XWMSFilamentColumns
{
    public static function expiresAt(string $name = 'expires_at', string $label = "Expires At",string $falseLabel = "Infinite",string $falseColor = "success",string $trueColor = "warning",): TextColumn
    {
        return static::date(
            $name,
            label: $label,
            falseLabel: $falseLabel,
            falseColor: $falseColor,
            trueColor: $trueColor,
        );
    }

    public static function date(
        string $name,
        string $label,
        string $falseLabel = 'Not Set',
        string $falseColor = 'danger',
        string $trueColor = 'success',
        string $dateFormat = 'j F Y \a\t H:i',
    ): TextColumn {
        return TextColumn::make($name)
            ->label($label)
            ->badge()
            ->color(fn ($state) => static::resolveColor($state, $falseColor, $trueColor))
            ->formatStateUsing(fn ($state) => static::formatDateState($state, $falseLabel, $dateFormat))
            ->default(false);
    }

    public static function dateInfo(
        string $name,
        string $label,
        string $falseLabel = 'Not Set',
        string $falseColor = 'gray',
        string $trueColor = 'info',
        string $dateFormat = 'j F Y \a\t H:i',
    ): TextColumn {
        return static::date(
            $name,
            $label,
            $falseLabel,
            $falseColor,
            $trueColor,
            $dateFormat,
        );
    }

    public static function badge(
        string $name,
        string $label,
        array $colors = ['default' => 'gray'],
        string $falseLabel = 'Not Set',
        string $falseColor = 'gray',
    ): TextColumn {
        return TextColumn::make($name)
            ->label($label)
            ->badge()
            ->color(fn ($state) => static::resolveBadgeColor($state, $colors, $falseColor))
            ->formatStateUsing(fn ($state) => static::formatBadgeState($state, $falseLabel))
            ->default(false);
    }

    // --- private helper methods ---

    private static function resolveColor(mixed $state, string $falseColor, string $trueColor): string
    {
        return $state === false ? $falseColor : $trueColor;
    }

    private static function formatDateState(mixed $state, string $falseLabel, string $dateFormat): string
    {
        if ($state === false) {
            return $falseLabel;
        }

        if ($state instanceof \DateTimeInterface) {
            return $state->format($dateFormat);
        }

        return (string)$state;
    }

    private static function resolveBadgeColor(mixed $state, array $colors, string $falseColor): string
    {
        // Als de waarde van $state voorkomt in de kleuren-array, gebruik dan die kleur
        if (array_key_exists($state, $colors)) {
            return $colors[$state];
        }

        // Als de waarde van $state gelijk is aan false, gebruik de fallbackkleur
        if ($state === false) {
            return $falseColor;
        }

        // Anders, gebruik de defaultkleur voor de badge
        return $colors[$state] ?? 'primary';
    }

    private static function formatBadgeState(mixed $state, string $falseLabel): string
    {
        return $state === false ? $falseLabel : (string)$state;
    }

    public static function withDefaultValue($column, string $defaultValue)
    {
        $column->default(false);
        return $column->formatStateUsing(fn ($state) => $state === false ? $defaultValue : (string)$state);
    }
}