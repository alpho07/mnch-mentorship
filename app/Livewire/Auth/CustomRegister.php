<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CustomRegister extends SimplePage {

    protected static string $view = 'livewire.auth.custom-register';
    public ?array $data = [];

    public function mount(): void {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->form->fill();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registration Logic
    // ─────────────────────────────────────────────────────────────────────────

    public function register(): ?RegistrationResponse {
        $data = $this->form->getState();

        // ── Extra duplicate checks (belt & suspenders alongside form rules) ──
        $this->guardAgainstDuplicates($data);

        // ── Build display name ───────────────────────────────────────────────
        $displayName = $this->buildDisplayName(
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name']
        );

        try {
            $user = DB::transaction(function () use ($data, $displayName) {
                $user = User::create([
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'name' => $displayName,
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'status' => 'active',
                ]);

                // Assign selected Spatie roles
                if (!empty($data['mentor_roles'])) {
                    $user->assignRole($data['mentor_roles']);
                }

                return $user;
            });

            event(new Registered($user));

            Filament::auth()->login($user);

            session()->regenerate();

            Notification::make()
                    ->success()
                    ->title('Registration Successful')
                    ->body("Welcome, {$user->first_name}! Your mentor account has been created.")
                    ->send();

            return app(RegistrationResponse::class);
        } catch (\Exception $e) {
            Notification::make()
                    ->danger()
                    ->title('Registration Failed')
                    ->body('An unexpected error occurred. Please try again or contact support.')
                    ->send();

            report($e);

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Form Schema
    // ─────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form {
        return $form
                        ->schema([
                            // ── Personal Information ─────────────────────────────────
                            Section::make('Personal Information')
                            ->icon('heroicon-o-user')
                            ->description('Enter your full name as it appears on official documents.')
                            ->compact()
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) =>
                                            $set('display_name', $this->buildDisplayName(
                                                            $get('first_name'),
                                                            $get('middle_name'),
                                                            $get('last_name'),
                                                    ))
                                    )
                                    ->extraInputAttributes(['class' => 'custom-input']),
                                    TextInput::make('middle_name')
                                    ->label('Middle Name')
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) =>
                                            $set('display_name', $this->buildDisplayName(
                                                            $get('first_name'),
                                                            $get('middle_name'),
                                                            $get('last_name'),
                                                    ))
                                    )
                                    ->extraInputAttributes(['class' => 'custom-input']),
                                    TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) =>
                                            $set('display_name', $this->buildDisplayName(
                                                            $get('first_name'),
                                                            $get('middle_name'),
                                                            $get('last_name'),
                                                    ))
                                    )
                                    ->extraInputAttributes(['class' => 'custom-input']),
                                ]),
                                Placeholder::make('display_name')
                                ->label('Display Name (auto-generated)')
                                ->content(fn(Get $get): string =>
                                        $this->buildDisplayName(
                                                $get('first_name'),
                                                $get('middle_name'),
                                                $get('last_name'),
                                        ) ?: '—'
                                )
                                ->helperText('This is how your name will appear across the platform.'),
                            ]),
                            // ── Contact Details ──────────────────────────────────────
                            Section::make('Contact Details')
                            ->icon('heroicon-o-phone')
                            ->compact()
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(table: User::class, column: 'email', ignoreRecord: true)
                                    ->validationMessages([
                                        'unique' => 'This email address is already registered. Please log in or use a different email.',
                                    ])
                                    ->extraInputAttributes(['class' => 'custom-input']),
                                    TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required()
                                    ->maxLength(20)
                                    ->unique(table: User::class, column: 'phone', ignoreRecord: true)
                                    ->validationMessages([
                                        'unique' => 'This phone number is already registered. Please log in or use a different number.',
                                    ])
                                    ->helperText('e.g. 0712345678 or +254712345678')
                                    ->extraInputAttributes(['class' => 'custom-input']),
                                ]),
                            ]),
                            // ── Role Selection ───────────────────────────────────────
                            Section::make('Mentor Role')
                            ->icon('heroicon-o-academic-cap')
                            ->description('Select the type of mentor role(s) you are registering for.')
                            ->compact()
                            ->schema([
                                CheckboxList::make('mentor_roles')
                                ->label('Select Role(s)')
                                ->options(function (): array {
                                    return Role::query()
                                                    ->whereIn('name', ['facility_mentor', 'national_mentor'])
                                                    ->pluck('name', 'name')
                                                    ->mapWithKeys(fn(string $name) => [
                                                        $name => match ($name) {
                                                            'facility_mentor' => 'Facility Mentor — Conduct mentorships at facility level',
                                                            'national_mentor' => 'National Mentor — Conduct mentorships at national/county level',
                                                            default => $name,
                                                        },
                                                            ])
                                                    ->toArray();
                                })
                                ->required()
                                ->columns(1)
                                ->bulkToggleable()
                                ->validationMessages([
                                    'required' => 'Please select at least one mentor role.',
                                ]),
                            ]),
                            // ── Password ─────────────────────────────────────────────
                            Section::make('Security')
                            ->icon('heroicon-o-lock-closed')
                            ->compact()
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->minLength(8)
                                    ->rules(['regex:/[A-Z]/', 'regex:/[0-9]/'])
                                    ->validationMessages([
                                        'regex' => 'Password must contain at least one uppercase letter and one number.',
                                        'min' => 'Password must be at least 8 characters long.',
                                    ])
                                    ->same('passwordConfirmation')
                                    ->extraInputAttributes(['class' => 'custom-input']),
                                    TextInput::make('passwordConfirmation')
                                    ->label('Confirm Password')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->dehydrated(false)
                                    ->extraInputAttributes(['class' => 'custom-input']),
                                ]),
                            ]),
                        ])
                        ->statePath('data');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a display name from name parts.
     */
    private function buildDisplayName(?string $first, ?string $middle, ?string $last): string {
        return trim(implode(' ', array_filter([
            $first ? ucfirst(strtolower(trim($first))) : null,
            $middle ? ucfirst(strtolower(trim($middle))) : null,
            $last ? ucfirst(strtolower(trim($last))) : null,
        ])));
    }

    /**
     * Runtime guard against email/phone duplicates.
     * The form `unique()` rule handles most cases, but this catches race conditions.
     */
    private function guardAgainstDuplicates(array $data): void {
        $errors = [];

        if (User::where('email', $data['email'])->exists()) {
            $errors['data.email'] = 'This email address is already registered. Please log in or use a different email.';
        }

        if (!empty($data['phone']) && User::where('phone', $data['phone'])->exists()) {
            $errors['data.phone'] = 'This phone number is already registered. Please log in or use a different number.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Page Config
    // ─────────────────────────────────────────────────────────────────────────

    public function getTitle(): string {
        return '';
    }

    public function hasLogo(): bool {
        return false;
    }
}
