<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Widgets\UserStatsOverview;
use App\Models\User;
use App\Models\County;
use App\Models\Subcounty;
use App\Models\Facility;
use App\Models\Cadre;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserResource extends Resource {

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?string $navigationLabel = 'All Users';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool {
        return auth()->check() && auth()->user()->hasRole(['super_admin', 'admin', 'division']);
    }

    public static function getNavigationBadge(): ?string {
        return (string) User::count();
    }

    public static function getNavigationBadgeColor(): string {
        return 'primary';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Form
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form {
        return $form->schema([
                            Forms\Components\Section::make('Personal Information')
                            ->icon('heroicon-o-user')
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('first_name')
                                ->label('First Name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(Get $get, Set $set) => static::updateDisplayName($get, $set)),
                                Forms\Components\TextInput::make('middle_name')
                                ->label('Middle Name')
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(Get $get, Set $set) => static::updateDisplayName($get, $set)),
                                Forms\Components\TextInput::make('last_name')
                                ->label('Last Name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn(Get $get, Set $set) => static::updateDisplayName($get, $set)),
                                Forms\Components\TextInput::make('name')
                                ->label('Display Name')
                                ->required()
                                ->readOnly()
                                ->helperText('Auto-generated from first, middle, last names'),
                                Forms\Components\TextInput::make('id_number')
                                ->label('National ID')
                                //->required()
                                ->unique(User::class, 'id_number', ignoreRecord: true)
                                ->maxLength(50),
                                Forms\Components\TextInput::make('phone')
                                ->label('Phone Number')
                                ->tel()
                                ->required()
                                ->maxLength(20),
                                Forms\Components\TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->unique(User::class, 'email', ignoreRecord: true)
                                ->maxLength(255),
                            ]),
                            Forms\Components\Section::make('Account & Roles')
                            ->icon('heroicon-o-shield-check')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                ->label('Account Status')
                                ->options([
                                    'active' => 'Active',
                                    'inactive' => 'Inactive',
                                    'suspended' => 'Suspended',
                                    'trainee' => 'Trainee (No Login)',
                                ])
                                ->required()
                                ->default('active')
                                ->live(),
                                Forms\Components\Select::make('roles')
                                ->label('System Roles')
                                ->relationship('roles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->optionsLimit(50)
                                ->hidden(fn(Get $get) => $get('status') === 'trainee'),
//                                Forms\Components\Select::make('permissions')
//                                ->label('Direct Permissions')
//                                ->relationship('permissions', 'name')
//                                ->multiple()
//                                ->preload()
//                                ->searchable()
//                                ->optionsLimit(100)
//                                ->helperText('Additional permissions beyond role permissions')
//                                ->hidden(fn(Get $get) => $get('status') === 'trainee'),
//                                Forms\Components\TextInput::make('password')
//                                ->label('Password')
//                                ->password()
//                                ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
//                                ->dehydrated(fn($state) => filled($state))
//                                ->required(fn(string $context): bool => $context === 'create')
//                                ->helperText(fn(string $context) => $context === 'create' ? 'Leave blank to auto-generate a random password' : 'Leave blank to keep existing password')
//                                ->hidden(fn(Get $get) => $get('status') === 'trainee'),
                            ]),
                            Forms\Components\Section::make('Organisational Assignment')
                            ->icon('heroicon-o-building-office-2')
                            ->description('Link this user to their facility, department, and cadre')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('facility_id')
                                ->label('Primary Facility')
                                ->relationship('facility', 'name')
                                ->searchable()
                                ->preload(false)
                                ->optionsLimit(100)
                                ->getOptionLabelFromRecordUsing(fn(Facility $f) =>
                                        $f->name . ($f->mfl_code ? " ({$f->mfl_code})" : '')
                                )
                                ->getSearchResultsUsing(fn(string $search) =>
                                        Facility::where('name', 'like', "%{$search}%")
                                        ->orWhere('mfl_code', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('name', 'id')
                                ),
                                Forms\Components\Select::make('department_id')
                                ->label('Department')
                                ->relationship('department', 'name')
                                ->searchable()
                                ->preload(),
                                Forms\Components\Select::make('cadre_id')
                                ->label('Cadre / Cadre Type')
                                ->relationship('cadre', 'name')
                                ->searchable()
                                ->preload(),
                            ]),
                            Forms\Components\Section::make('Geographic Access Scope')
                            ->icon('heroicon-o-map')
                            ->description('Counties / sub-counties / facilities this user can access. Leave blank for no geo-restriction.')
                            ->collapsed()
                            ->columns(1)
                            ->schema([
                                Forms\Components\Select::make('counties')
                                ->label('Counties')
                                ->options(County::orderBy('name')->pluck('name', 'id'))
                                ->multiple()
                                ->searchable()
                                ->preload(false),
                                Forms\Components\Select::make('subcounties')
                                ->label('Sub-Counties')
                                ->options(Subcounty::orderBy('name')->pluck('name', 'id'))
                                ->multiple()
                                ->searchable()
                                ->preload(false),
                                Forms\Components\Select::make('facilities')
                                ->label('Additional Facilities')
                                ->options(Facility::orderBy('name')->pluck('name', 'id'))
                                ->multiple()
                                ->searchable()
                                ->preload(false),
                            ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Table
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table {
        return $table
                        ->defaultSort('created_at', 'desc')
                        ->striped()
                        ->columns([
                            // ── Name + email description ───────────────────────────────
                            Tables\Columns\TextColumn::make('name')
                            ->label('Name')
                            ->searchable(['first_name', 'last_name', 'name', 'email', 'phone', 'id_number'])
                            ->sortable()
                            ->weight('semibold')
                            ->formatStateUsing(fn(User $record) => $record->full_name)
                            ->description(fn(User $record) => $record->email ?? '—'),
                            Tables\Columns\TextColumn::make('phone')
                            ->label('Phone')
                            ->searchable()
                            ->copyable()
                            ->copyMessage('Phone copied')
                            ->placeholder('—'),
                            Tables\Columns\TextColumn::make('id_number')
                            ->label('National ID')
                            ->searchable()
                            ->copyable()
                            ->copyMessage('ID copied')
                            ->placeholder('—')
                            ->toggleable(),
                            // ── Facility + county description ──────────────────────────
                            // Full chain loaded eagerly. Every step is null-safe (?->)
                            // so users with no facility, or facilities with no subcounty,
                            // or subcounties with no county all render gracefully.
                            Tables\Columns\TextColumn::make('facility.name')
                            ->label('Facility')
                            ->searchable()
                            ->sortable()
                            ->description(function (User $record): ?string {
                                $parts = array_filter([
                                    $record->facility?->mfl_code ? "MFL: {$record->facility->mfl_code}" : null,
                                    $record->facility?->subcounty?->county?->name,
                                ]);
                                return $parts ? implode(' · ', $parts) : null;
                            })
                            ->placeholder('—')
                            ->limit(30),
                            Tables\Columns\TextColumn::make('cadre.name')
                            ->label('Cadre')
                            ->badge()
                            ->color('gray')
                            ->placeholder('—')
                            ->toggleable(),
                            // ── Role badges ───────────────────────────────────────────
                            Tables\Columns\TextColumn::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->separator(',')
                            ->color(fn(string $state): string => match (strtolower($state)) {
                                        'super_admin', 'super admin' => 'danger',
                                        'admin' => 'rose',
                                        'division', 'division lead' => 'warning',
                                        'mentor' => 'info',
                                        'co_mentor', 'co-mentor' => 'purple',
                                        'mentee' => 'success',
                                        default => 'gray',
                                    })
                            ->placeholder('No role'),
                            // ── Status badge ──────────────────────────────────────────
                            Tables\Columns\TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn(?string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'gray',
                                        'suspended' => 'danger',
                                        'trainee' => 'warning',
                                        default => 'gray',
                                    })
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'suspended' => 'Suspended',
                                        'trainee' => 'Trainee',
                                        default => ucfirst($state ?? 'Unknown'),
                                    })
                            ->sortable(),
                            Tables\Columns\TextColumn::make('created_at')
                            ->label('Registered')
                            ->date('d M Y')
                            ->sortable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        ])

                        // ── Filters ───────────────────────────────────────────────────
                        ->filters([
                            SelectFilter::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                                'trainee' => 'Trainee',
                            ])
                            ->multiple(),
                            SelectFilter::make('roles')
                            ->label('Role')
                            ->relationship('roles', 'name')
                            ->searchable()
                            ->preload()
                            ->multiple(),
                            SelectFilter::make('facility_id')
                            ->label('Facility')
                            ->searchable()
                            ->preload(false)
                            ->getSearchResultsUsing(fn(string $search) =>
                                    Facility::where('name', 'like', "%{$search}%")
                                    ->orWhere('mfl_code', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->pluck('name', 'id')
                            ),
                            SelectFilter::make('cadre_id')
                            ->label('Cadre')
                            ->relationship('cadre', 'name')
                            ->searchable()
                            ->preload(),
                            Filter::make('county')
                            ->label('County')
                            ->form([
                                Forms\Components\Select::make('county_id')
                                ->label('County')
                                ->options(County::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload(false),
                            ])
                            ->query(function (Builder $query, array $data): Builder {
                                if (empty($data['county_id'])) {
                                    return $query;
                                }
                                return $query->whereHas('facility.subcounty', fn($q) =>
                                                $q->where('county_id', $data['county_id'])
                                        );
                            })
                            ->indicateUsing(fn(array $data): ?string =>
                                    $data['county_id'] ? 'County: ' . (County::find($data['county_id'])?->name ?? '') : null
                            ),
                            Filter::make('has_no_role')
                            ->label('No Role Assigned')
                            ->toggle()
                            ->query(fn(Builder $q) => $q->whereDoesntHave('roles')),
                            Filter::make('registered_this_month')
                            ->label('Registered This Month')
                            ->toggle()
                            ->query(fn(Builder $q) => $q->whereMonth('created_at', now()->month)
                                    ->whereYear('created_at', now()->year)),
                        ])
                        ->filtersFormColumns(3)

                        // ── Row actions ───────────────────────────────────────────────
                        ->actions([
                            Tables\Actions\ActionGroup::make([
                                Tables\Actions\ViewAction::make()
                                ->label('View Profile')
                                ->icon('heroicon-o-eye'),
                                Tables\Actions\EditAction::make()
                                ->icon('heroicon-o-pencil'),
                                // ── Quick status toggle ────────────────────────────────
                                Tables\Actions\Action::make('activate')
                                ->label('Activate')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->visible(fn(User $record) => $record->status !== 'active')
                                ->action(function (User $record) {
                                    $record->update(['status' => 'active']);
                                    Notification::make()->success()->title("{$record->full_name} activated")->send();
                                }),
                                Tables\Actions\Action::make('suspend')
                                ->label('Suspend')
                                ->icon('heroicon-o-no-symbol')
                                ->color('danger')
                                ->visible(fn(User $record) => $record->status === 'active')
                                ->requiresConfirmation()
                                ->modalHeading('Suspend User')
                                ->modalDescription(fn(User $record) => "Suspend {$record->full_name}? They will no longer be able to log in.")
                                ->action(function (User $record) {
                                    $record->update(['status' => 'suspended']);
                                    Notification::make()->warning()->title("{$record->full_name} suspended")->send();
                                }),
                                // ── Reset password ─────────────────────────────────────
                                Tables\Actions\Action::make('reset_password')
                                ->label('Reset Password')
                                ->icon('heroicon-o-key')
                                ->color('warning')
                                ->visible(fn(User $record) => $record->status !== 'trainee')
                                ->requiresConfirmation()
                                ->modalHeading('Reset Password')
                                ->modalDescription('A new random password will be generated and displayed once.')
                                ->action(function (User $record) {
                                    $newPassword = Str::random(10);
                                    $record->update(['password' => Hash::make($newPassword)]);

                                    Notification::make()
                                            ->title('Password Reset')
                                            ->body("New password for **{$record->full_name}**: `{$newPassword}`\n\nCopy and share securely — it will not be shown again.")
                                            ->success()
                                            ->persistent()
                                            ->send();
                                }),
                                Tables\Actions\DeleteAction::make()
                                ->requiresConfirmation(),
                            ]),
                        ])

                        // ── Bulk actions ──────────────────────────────────────────────
                        ->bulkActions([
                            Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\BulkAction::make('bulk_activate')
                                ->label('Activate Selected')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->requiresConfirmation()
                                ->action(function (Collection $records) {
                                    $count = $records->count();
                                    User::whereIn('id', $records->pluck('id'))->update(['status' => 'active']);
                                    Notification::make()->success()->title("{$count} users activated")->send();
                                })
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_suspend')
                                ->label('Suspend Selected')
                                ->icon('heroicon-o-no-symbol')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading('Suspend Selected Users')
                                ->modalDescription('These users will no longer be able to log in.')
                                ->action(function (Collection $records) {
                                    $count = $records->count();
                                    User::whereIn('id', $records->pluck('id'))->update(['status' => 'suspended']);
                                    Notification::make()->warning()->title("{$count} users suspended")->send();
                                })
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_assign_role')
                                ->label('Assign Role')
                                ->icon('heroicon-o-user-plus')
                                ->color('info')
                                ->form([
                                    Forms\Components\Select::make('role')
                                    ->label('Role to Assign')
                                    ->options(Role::orderBy('name')->pluck('name', 'name'))
                                    ->required()
                                    ->searchable(),
                                    Forms\Components\Toggle::make('replace_existing')
                                    ->label('Replace existing roles (sync)')
                                    ->default(false),
                                ])
                                ->action(function (Collection $records, array $data) {
                                    $count = 0;
                                    foreach ($records as $user) {
                                        if ($user->status === 'trainee')
                                            continue;
                                        $data['replace_existing'] ? $user->syncRoles([$data['role']]) : $user->assignRole($data['role']);
                                        $count++;
                                    }
                                    Notification::make()->success()->title("Role assigned to {$count} users")->send();
                                })
                                ->requiresConfirmation()
                                ->deselectRecordsAfterCompletion(),
                                Tables\Actions\BulkAction::make('bulk_export_csv')
                                ->label('Export to CSV')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('gray')
                                ->action(function (Collection $records) {
                                    $ids = $records->pluck('id')->toArray();
                                    return response()->streamDownload(function () use ($ids) {
                                                $out = fopen('php://output', 'w');
                                                fputcsv($out, [
                                                    'ID', 'Full Name', 'First', 'Middle', 'Last',
                                                    'National ID', 'Email', 'Phone', 'Status',
                                                    'Roles', 'Facility', 'MFL Code', 'County',
                                                    'Cadre', 'Registered At',
                                                ]);

                                                User::whereIn('id', $ids)
                                                        ->with(['facility.subcounty.county', 'cadre', 'roles'])
                                                        ->cursor()
                                                        ->each(function (User $u) use ($out) {
                                                            fputcsv($out, [
                                                                $u->id,
                                                                $u->full_name,
                                                                $u->first_name,
                                                                $u->middle_name,
                                                                $u->last_name,
                                                                $u->id_number,
                                                                $u->email,
                                                                $u->phone,
                                                                $u->status,
                                                                $u->roles->pluck('name')->implode(', '),
                                                                $u->facility?->name,
                                                                $u->facility?->mfl_code,
                                                                $u->facility?->subcounty?->county?->name,
                                                                $u->cadre?->name,
                                                                $u->created_at?->format('Y-m-d H:i'),
                                                            ]);
                                                        });

                                                fclose($out);
                                            }, 'users-export-' . now()->format('Ymd-His') . '.csv', [
                                                'Content-Type' => 'text/csv',
                                    ]);
                                }),
                                Tables\Actions\DeleteBulkAction::make()
                                ->requiresConfirmation(),
                            ]),
                        ])
                        ->emptyStateHeading('No users found')
                        ->emptyStateDescription('Try adjusting your filters or create a new user.')
                        ->emptyStateIcon('heroicon-o-users');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pages & Relations
    // ─────────────────────────────────────────────────────────────────────────

    public static function getRelations(): array {
        return [];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array {
        return [
            UserStatsOverview::class,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    public static function updateDisplayName(Get $get, Set $set): void {
        $parts = array_filter([
            trim($get('first_name') ?? ''),
            trim($get('middle_name') ?? ''),
            trim($get('last_name') ?? ''),
        ]);
        $set('name', implode(' ', $parts));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // afterSave hook
    //
    // Called by CreateUser::handleRecordCreation() and EditUser::handleRecordUpdate()
    // after the user model is persisted. Syncs pivot relationships (roles, org
    // unit access scopes) that cannot be mass-assigned on the model directly.
    // ─────────────────────────────────────────────────────────────────────────

    public static function afterSave(User $record, array $data): void {
        // Sync Spatie roles
        if (isset($data['roles'])) {
            $record->syncRoles($data['roles']);
        }

        // Sync direct permissions (beyond role-inherited permissions)
        if (isset($data['permissions'])) {
            $record->syncPermissions($data['permissions']);
        }

        // Sync geographic access scope pivots
        $record->counties()->sync($data['counties'] ?? []);
        $record->subcounties()->sync($data['subcounties'] ?? []);
        $record->facilities()->sync($data['facilities'] ?? []);
    }
}
