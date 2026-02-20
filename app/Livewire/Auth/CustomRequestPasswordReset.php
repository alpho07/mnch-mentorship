<?php

namespace App\Livewire\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;

class CustomRequestPasswordReset extends SimplePage {

    protected static string $view = 'livewire.auth.custom-request-password-reset';
    public ?array $data = [];

    public function mount(): void {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function request(): void {
        $data = $this->form->getState();

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                $data,
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                    ->title(__($status))
                    ->danger()
                    ->send();

            return;
        }

        Notification::make()
                ->title(__($status))
                ->success()
                ->send();

        $this->form->fill();
    }

    public function form(Form $form): Form {
        return $form
                        ->schema([
                            TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->autofocus()
                            ->extraInputAttributes(['class' => 'custom-input']),
                        ])
                        ->statePath('data');
    }

    public function getTitle(): string {
        return '';
    }

    public function hasLogo(): bool {
        return false;
    }
}
