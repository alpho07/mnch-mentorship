<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class CustomRegister extends SimplePage {

    protected static string $view = 'livewire.auth.custom-register';
    public ?array $data = [];

    public function mount(): void {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function register(): ?RegistrationResponse {
        $data = $this->form->getState();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        Filament::auth()->login($user);

        session()->regenerate();

        return app(RegistrationResponse::class);
    }

    public function form(Form $form): Form {
        return $form
                        ->schema([
                            TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->extraInputAttributes(['class' => 'custom-input']),
                            TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class)
                            ->extraInputAttributes(['class' => 'custom-input']),
                            TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->same('passwordConfirmation')
                            ->extraInputAttributes(['class' => 'custom-input']),
                            TextInput::make('passwordConfirmation')
                            ->label('Confirm password')
                            ->password()
                            ->required()
                            ->dehydrated(false)
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
