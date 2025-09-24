<?php

namespace XWMS\Package\Filament;

use App\Filament\Account\Resources\AccountResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Form;
use XWMS\Package\Filament\AccountTrait;

class AccountEditParent extends EditRecord
{
    use AccountTrait;
    protected static string $resource = AccountResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function mount($record): void
    {
        abort_unless(intval($record) === Auth::id(), 403);
        parent::mount($record);
    }

    public function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['name'])) {
            $parts = explode(' ', $data['name'], 2);
            $data['frontname'] = $parts[0] ?? '';
            $data['lastname'] = $parts[1] ?? '';
        }

        return $data;
    }

    public function mutateFormDataBeforeSave(array $data): array
    {
        $data['name'] = trim(($data['frontname'] ?? '') . ' ' . ($data['lastname'] ?? ''));
        unset($data['frontname'], $data['lastname']); // deze bestaan niet in je model

        return $data;
    }

    public bool $emailCodeSent = false;
    public bool $emailCodeSentPassword = false;
    public $email_key = "new_email";
    public function form(Form $form): Form
    {
        return $this->accountForm($this, $form);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
