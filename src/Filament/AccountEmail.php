<?php

namespace XWMS\Package\Filament;

use XWMS\Package\Helpers\RateLimit;
use XWMS\Package\Helpers\Mail;
use XWMS\Package\Helpers\VerificationHelper;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

use XWMS\Package\Helpers\Filament AS FilamentHelper;

class AccountEmail
{
    public static function verifyEmail($component, string $email, $user)
    {
        $same = false;

        if ($email == $user->email) $same = true;
        if ($user->second_email !== null && $email === $user->second_email) $same = true;

        if ($same){
            Notification::make()
                ->title('This is the same email')
                ->warning()
                ->send();
            return false;
        }

        return true;
    }
    public static function sendVerificationCode($user, string $category, string $email): void
    {
        RateLimit::checkRateLimit($category, 46, ['message' => 'before requesting a new code.']);

        $code = VerificationHelper::send(
            userId: $user->id,
            category: $category,
            email: $email,
            validMinutes: 10
        )->code;

        Mail::sendVerificationCode($email, $code, [
            'name'  => $user->name,
            'subject'  => 'Confirm your email address',
            'description_short' => 'Use this code to confirm your email.',
            'description'     => 'Valid for 10 minutes. Don’t share it.',
            'description_second'   => 'Didn’t request this? Ignore this message.',
        ]);
    }

    public static function verifyCode(int $userId, string $category, string $code): void
    {
        VerificationHelper::verify(
            userId: $userId,
            category: $category,
            inputCode: $code,
            rateLimitSeconds: 5,
            maxAttempts: 5
        );
    }




    public static function submitStartEmailChange($component, ?string $invalidemailMessage = null): bool
    {
        return FilamentHelper::handleSecure(function () use($component, $invalidemailMessage) {

            $state = $component->form->getState();
            $newEmail = $state[$component->email_key] ?? null;

            if (!$newEmail || ! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $component->addError($component->email_key, $invalidemailMessage ?? "Please enter a valid email address so we can reach you.");
            }

            $userModel = config('xwms.models.User', \App\Models\User::class);
            $user = $userModel::find(Auth::id());

            $check = self::verifyEmail($component, $newEmail, $user);
            if (!$check) return false;

            self::sendVerificationCode(
                user: $user,
                category: 'change_email',
                email: $newEmail
            );

            $component->emailCodeSent = true;

            Notification::make()
                ->title('Verification code sent')
                ->body('We’ve sent a confirmation code to your new email address. Please enter the code to complete the update.')
                ->icon('heroicon-o-envelope')
                ->iconColor('primary')
                ->duration(8000)
                ->color('success')
                ->send();

            return true;
        });
    }

    public static function submitVerifyNewEmail($component, ?string $missingCodeMessage = null): bool
    {
        $state = $component->form->getState();
        $CodeString = "email_code";

        $newEmail = $state[$component->email_key] ?? null;
        $code = $state[$CodeString] ?? null;

        if (! $newEmail || ! $code) {
            $component->addError($CodeString, $missingCodeMessage ?? "Please enter the verification code you received.");
            return false;
        }

        try {
            self::verifyCode(
                userId: Auth::id(),
                category: 'change_email',
                code: $code
            );

            $userModel = config('xwms.models.User', \App\Models\User::class);
            $user = $userModel::find(Auth::id());
            $user->email = $newEmail;
            $user->save();

            $component->emailCodeSent = false;
            $component->form->fill(
                $component->mutateFormDataBeforeFill(
                    $user->fresh()->toArray()
                )
            );


            Notification::make()
                ->title('Email updated successfully')
                ->body('Your new email address has been verified and saved. Future communication will be sent to this address.')
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->duration(8000)
                ->color('success')
                ->send();

            return true;
        } catch (\Throwable $e) {
            $component->addError('email_code', $e->getMessage());
            return false;
        }
    }
}