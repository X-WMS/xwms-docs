<?php

namespace XWMS\Package\Filament\Forms;

use Closure;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;

class Morph
{
    public static function json(
        string $fieldName = 'payload',
        string $label = 'Payload',
        string $description = 'Optionele extra data voor deze assignment. Wordt opgeslagen als JSON.',
        array $default = [],
    ): Repeater {
        return Repeater::make($fieldName)
            ->label($label)
            ->helperText($description)
            ->columns(2)
            ->columnSpanFull()
            ->afterStateHydrated(function (Repeater $component, $state) {
                if (is_string($state)) {
                    $decoded = json_decode($state, true);
                    $component->state($decoded ?? []);
                }
            })
            ->dehydrateStateUsing(function ($state) {
                return json_encode($state);
            })
            ->schema([
                TextInput::make('key')
                    ->label('Key')
                    ->required(),

                TextInput::make('value')
                    ->label('Value')
                    ->required(),
            ])
            ->default($default);
    }


    public static function dynamic(
        string $typeField = 'owner_type',
        string $idField = 'owner_id',
        string $label = 'Select an Owner',
        array $modelOptions = [
            'App\\Models\\Client' => 'Client',
            'App\\Models\\User' => 'User',
        ],
        string|Closure $displayColumn = 'name',
        int $limit = 50,
        string $description = 'Select an owner based on the selected type.',
    ): Fieldset {
        return Fieldset::make($label)->schema([
            Select::make($typeField)
                ->label(ucwords(str_replace('_', ' ', $typeField)))
                ->options($modelOptions)
                ->preload()
                ->searchable()
                ->reactive()
                ->afterStateUpdated(fn (callable $set) => $set($idField, null))
                ->nullable(),

            Select::make($idField)
                ->label(ucwords(str_replace('_', ' ', $idField)))
                ->options(function (callable $get) use ($typeField, $displayColumn, $limit) {
                    $ownerType = $get($typeField);
                    if (! $ownerType || ! class_exists($ownerType)) {
                        return [];
                    }

                    $query = $ownerType::query()->limit($limit)->get();

                    // If displayColumn is a closure (e.g., to customize label formatting)
                    if ($displayColumn instanceof Closure) {
                        return $query->mapWithKeys(function ($model) use ($displayColumn) {
                            return [$model->id => $displayColumn($model)];
                        })->toArray();
                    }

                    // Else use regular pluck
                    return $query->pluck($displayColumn, 'id')->toArray();
                })
                ->preload()
                ->searchable()
                ->visible(fn (callable $get) => filled($get($typeField)))
                ->required(fn (callable $get) => filled($get($typeField)))
                ->helperText($description)
                ->nullable(),
        ]);
    }
}
