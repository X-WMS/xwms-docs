<?php

namespace XWMS\Package\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';
    protected static ?string $title = 'Active Sessions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('device_icon')
                    ->label('Device')
                    ->state(function ($record) {
                        return str_contains(strtolower($record->user_agent), 'mobile')
                            ? 'mobile'
                            : 'desktop';
                    })
                    ->icon(fn ($state) => $state === 'mobile'
                        ? 'heroicon-o-device-phone-mobile'
                        : 'heroicon-o-computer-desktop'
                    )
                    ->tooltip(fn ($record) => $record->user_agent),

                Tables\Columns\TextColumn::make('os')
                    ->label('OS')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $this->getPlatform($record->user_agent)),

                Tables\Columns\TextColumn::make('browser')
                    ->label('Browser')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $this->getBrowser($record->user_agent)),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('last_active')
                    ->label('Last active')
                    ->getStateUsing(function ($record) {
                        return $record->id === session()->getId()
                            ? 'This device'
                            : Carbon::createFromTimestamp($record->last_activity)->diffForHumans();
                    }),
            ])
            ->filters([]) // Voeg filters toe als je bv. OS wilt filteren
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->id !== session()->getId())
                    ->tooltip('Are you sure you wan\'t to Delete this session?'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deleteAllExceptCurrent')
                    ->label('Delete Selected Sessions')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function ($records) {
                        $currentSessionId = session()->getId();
                        \XWMS\Package\Models\Session::query()
                            ->where('user_id', Auth::id())
                            ->where('id', '!=', $currentSessionId)
                            ->delete();
                    })
                    ->icon('heroicon-o-trash')
                    ->hidden(fn () => \XWMS\Package\Models\Session::where('user_id', Auth::id())->count() <= 1),
            ]);
    }

    private function getPlatform(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') => 'iOS',
            default => 'Unknown',
        };
    }

    private function getBrowser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'OPR') => 'Opera',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') && !str_contains($userAgent, 'Chrome') => 'Safari',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident') => 'Internet Explorer',
            default => 'Unknown',
        };
    }
}
