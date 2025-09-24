<?php

namespace XWMS\Package\Helpers;

use Filament\Notifications\Notification;

class Filament
{
    public static function handleSecure(callable $callback)
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Unable to proccess the action')
                ->body('An error occurred: ' . $e->getMessage())
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('danger')
                ->color('danger')
                ->persistent()
                ->send();

            return false;
        }
    }
}
