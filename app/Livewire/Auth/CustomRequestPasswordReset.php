<?php

namespace App\Livewire\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CustomRequestPasswordReset extends SimplePage {

    protected static string $view = 'livewire.auth.custom-request-password-reset';
    public ?array $data = [];

    public function mount(): void {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function sendResetLink(): void {
        $data = $this->form->getState();

        // Validate email exists
        if (!\App\Models\User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                        'data.email' => 'We cannot find a user with that email address.',
            ]);
        }

        $status = Password::broker(Filament::getAuthPasswordBroker())
                ->sendResetLink(['email' => $data['email']]);

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                    ->title(__($status))
                    ->danger()
                    ->send();

            return;
        }

        Notification::make()
                ->title('Password reset link sent successfully.')
                ->success()
                ->send();
    }

    public function form(Form $form): Form {
        return $form
                        ->schema([
                            TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->exists('users', 'email')
                            ->autocomplete('email'),
                        ])
                        ->statePath('data');
    }
}
