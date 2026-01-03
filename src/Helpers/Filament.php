<?php

namespace XWMS\Package\Helpers;

use Filament\Notifications\Notification;
use Filament\Actions\Action;
use XWMS\Package\Controllers\Api\XwmsApiHelper;

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

    public static function accountActions(array $options = []): array
    {
        return [
            self::openXwmsAccountAction($options['open'] ?? []),
            self::syncXwmsAccountAction($options['sync'] ?? []),
        ];
    }

    public static function appendAccountActions(array $actions, array $options = []): array
    {
        return array_merge($actions, self::accountActions($options));
    }

    public static function openXwmsAccountAction(array $options = []): Action
    {
        $url = $options['url'] ?? config('xwms.account_url', 'https://xwms.nl/account/general');

        return Action::make($options['name'] ?? 'openXwms')
            ->label($options['label'] ?? 'Edit profile on XWMS')
            ->icon($options['icon'] ?? 'heroicon-m-arrow-top-right-on-square')
            ->color($options['color'] ?? 'primary')
            ->url($url)
            ->openUrlInNewTab();
    }

    public static function syncXwmsAccountAction(array $options = []): Action
    {
        return Action::make($options['name'] ?? 'fetchFromXwms')
            ->label($options['label'] ?? 'Sync data from XWMS')
            ->icon($options['icon'] ?? 'heroicon-m-arrow-path')
            ->color($options['color'] ?? 'gray')
            ->action(function () use ($options) {
                $result = XwmsApiHelper::syncAuthenticatedUserFromXwms($options['sync_options'] ?? []);

                if (($result['status'] ?? null) === 'success') {
                    Notification::make()
                        ->title($options['success_title'] ?? 'Account updated')
                        ->body($options['success_body'] ?? 'Your account has been synchronized with XWMS.')
                        ->success()
                        ->seconds($options['success_seconds'] ?? 8)
                        ->send();
                } else {
                    Notification::make()
                        ->title($options['error_title'] ?? 'Sync failed')
                        ->body($options['error_body'] ?? ($result['message'] ?? 'We could not update your account.'))
                        ->danger()
                        ->seconds($options['error_seconds'] ?? 8)
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->modalHeading($options['modal_heading'] ?? 'Sync account data')
            ->modalDescription($options['modal_description'] ?? 'We will fetch your latest profile details from XWMS and update your account.')
            ->modalSubmitActionLabel($options['modal_submit'] ?? 'Sync now');
    }
}
