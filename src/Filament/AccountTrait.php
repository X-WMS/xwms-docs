<?php

namespace XWMS\Package\Filament;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use XWMS\Package\Filament\AccountEmail;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;

trait AccountTrait
{
    protected function accountGeneralDefaultGridTop($component): Forms\Components\Grid
    {
        return Forms\Components\Grid::make(12)
        ->schema([
            // Linkerkant: ronde avatar
            Forms\Components\FileUpload::make('img')
                ->label('Profile Photo')
                ->avatar()
                ->image()
                ->imageEditor()
                ->imageCropAspectRatio('1:1')
                ->imageResizeTargetWidth(512)
                ->imageResizeTargetHeight(512)
                ->maxSize(3072) // 3MB
                ->panelAspectRatio('1:1')
                ->imagePreviewHeight('180')
                ->disk('public')
                ->directory('users')
                ->columnSpan(6),

            Forms\Components\Grid::make()
                ->columns(1)
                ->columnSpan(6)
                ->schema([
                    Forms\Components\TextInput::make('frontname')
                        ->label('First name')
                        ->placeholder('e.g. John')
                        ->helperText('Enter your given name. Only letters are allowed.')
                        ->maxLength(64)
                        ->minLength(3)
                        ->required()
                        ->autocomplete('given-name')
                        ->rule(['required', 'string', 'alpha', 'min:3', 'max:64']),

                    Forms\Components\TextInput::make('lastname')
                        ->label('Last name')
                        ->placeholder('e.g. Doe')
                        ->helperText('Enter your surname or family name.')
                        ->maxLength(64)
                        ->minLength(3)
                        ->required()
                        ->autocomplete('family-name')
                        ->rule(['required', 'string', 'alpha', 'min:3', 'max:64']),

                ]),
        ]);
    }

    protected function accountGeneralDefaultGridBottom($component): Forms\Components\Grid
    {
        return Forms\Components\Grid::make()
        ->schema([
            Forms\Components\TextInput::make('email')
                ->label('Primary email address')
                ->placeholder('e.g. johndoe@example.com')
                ->helperText('This is your main registered email address.')
                ->email()
                ->required()
                ->disabled()
                ->autocomplete('email')
                ->maxLength(128)
                ->rule(['required', 'email', 'max:128']),

            Forms\Components\Select::make('my_country_id')
                ->label('Country')
                ->relationship('country', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Select your country')
                ->helperText('Choose the country you currently live in.')
                ->required()
                ->rule(['required', 'exists:countries,id']),
        ])
        ->columns(2);
    }

    protected function accountGeneralDefaultGridExtended($component): \Filament\Forms\Components\Component
    {
        return \Filament\Forms\Components\Group::make([])->visible(false);
    }

    protected function accountGeneralDefaultSection($component): Forms\Components\Section
    {
        return Forms\Components\Section::make("General Account information")->schema([
            $this->accountGeneralDefaultGridTop($component),
            $this->accountGeneralDefaultGridBottom($component),
            $this->accountGeneralDefaultGridExtended($component),
        ])->columns(1);
    }

    protected function accountForm($component, Form $form): Form
    {
        return $form
            ->schema([
                $this->accountGeneralDefaultSection($component),
                // $this->accountSecuritySection($component),
            ]);
    }





    protected function accountSecurityChangeEmail($component): Forms\Components\Section
    {
        return Forms\Components\Section::make('Change Email Address')
        ->description('Update your email address. A confirmation code will be sent to your new address.')
        ->collapsible()
        ->collapsed()
        ->schema([
            Forms\Components\Grid::make(1)->schema([
                Forms\Components\TextInput::make('new_email')
                    ->label('New Email Address')
                    ->placeholder('e.g. user@example.com')
                    ->helperText('We will send a verification code to this email.')
                    ->email()
                    ->visible(fn (Get $get) => true)
                    ->dehydrated()
                    ->statePath('new_email'),

                Forms\Components\TextInput::make('email_code')
                    ->label('Verification Code')
                    ->placeholder('Enter the code from your email')
                    ->helperText('Check your new email for a verification code.')
                    ->visible(fn (Get $get) => $component->emailCodeSent)
                    ->dehydrated()
                    ->statePath('email_code'),
            ]),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('SetNewEmail')
                    ->label('Send Verification Code')
                    ->button()
                    ->visible(fn (Get $get) => ! $component->emailCodeSent)
                    ->action('submitStartEmailChange'),

                Forms\Components\Actions\Action::make('VerifyNewEmail')
                    ->label('Verify & Update Email')
                    ->button()
                    ->color('success')
                    ->visible(fn (Get $get) => $component->emailCodeSent)
                    ->action('submitVerifyNewEmail')
                    ->requiresConfirmation(),

                Forms\Components\Actions\Action::make('CancelNewEmail')
                    ->label('Cancel')
                    ->button()
                    ->color('secondary')
                    ->visible(fn (Get $get) => $component->emailCodeSent)
                    ->action(function (Set $set) use($component) {
                        $component->emailCodeSent = false;
                        $set('emailCodeSent', false);
                        $set('email_code', '');
                        $set('new_email', '');
                    }),
            ]),
        ]);
    }

    public function submitStartEmailChange(): void
    {
        AccountEmail::submitStartEmailChange($this);
    }

    public function submitVerifyNewEmail(): void
    {
        AccountEmail::submitVerifyNewEmail($this);
    }

    public function changePassword(): void
    {
        $state = $this->form->getState();

        $password = $state['new_password'] ?? null;
        $passwordRepeat = $state['new_password_confirmation'] ?? null;

        if (empty($password)) {
            $this->addError('new_password', 'Please enter a new password.');
            return;
        }

        if (empty($passwordRepeat)) {
            $this->addError('new_password_confirmation', 'Please confirm your new password.');
            return;
        }

        if ($password !== $passwordRepeat) {
            $this->addError('new_password_confirmation', 'Passwords do not match.');
            return;
        }

        $this->record->update([
            'password' => Hash::make($password),
        ]);

        $this->form->fill([
            'new_password' => '',
            'new_password_confirmation' => '',
        ]);

        Notification::make()
            ->title('Password Changed')
            ->body('Your password has been successfully updated.')
            ->icon('heroicon-o-lock-closed')
            ->iconColor('success')
            ->duration(8000)
            ->color('success')
            ->send();
    }


    protected function accountSecurityChangePassword($component): Forms\Components\Section
    {
        return Forms\Components\Section::make('Change Password')
        ->description('Update your account password securely. Make sure your new password is strong and unique.')
        ->collapsible()
        ->collapsed()
        ->schema([
            Forms\Components\TextInput::make('new_password')
                ->label('New Password')
                ->password()
                ->placeholder('Choose a new password')
                ->minLength(8)
                ->helperText('Must be at least 8 characters and contain letters and numbers.'),

            Forms\Components\TextInput::make('new_password_confirmation')
                ->label('Confirm New Password')
                ->password()
                ->placeholder('Repeat your new password')
                ->same('new_password')
                ->helperText('Make sure this matches the new password.'),

            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('SetNewPassword')
                    ->label('Set New Password')
                    ->button()
                    ->action('changePassword')
            ]),
        ])
        ->columns(2);
    }

    protected function accountSecurityExtended($component): \Filament\Forms\Components\Component
    {
        return \Filament\Forms\Components\Group::make([])->visible(false);
    }
    
    protected function accountSecuritySection($component): Forms\Components\Section
    {
        return Forms\Components\Section::make('Security Settings')
        ->schema([
            $this->accountSecurityChangePassword($component),
            $this->accountSecurityChangeEmail($component),
            $this->accountSecurityExtended($component),
        ]);
    }
}