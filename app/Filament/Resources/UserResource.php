<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\County;
use App\Models\Subcounty;
use App\Models\Facility;
use App\Models\Cadre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'User Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')->label('First Name')->required(),
                Forms\Components\TextInput::make('middle_name')->label('Middle Name'),
                Forms\Components\TextInput::make('last_name')->label('Last Name')->required(),
                Forms\Components\TextInput::make('name')->label('Display Name')->required(),
                Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('id_number')->label('ID Number')->required()->maxLength(50),
                Forms\Components\TextInput::make('phone')->label('Phone Number')->required()->maxLength(20),
                Forms\Components\Select::make('cadre_id')
                    ->label('Cadre')
                    ->options(Cadre::all()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ])
                    ->default('active')
                    ->required(),
                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => !empty($state) ? bcrypt($state) : null)
                    ->required(fn ($context) => $context === 'create')
                    ->maxLength(255),

                Forms\Components\Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->options(Role::all()->pluck('name', 'name'))
                    ->required(),

                Forms\Components\Select::make('org_level')
                    ->label('Organization Level')
                    ->options([
                        'above_site' => 'Above Site (Super Admin/National/Division)',
                        'county'     => 'County',
                        'subcounty'  => 'Subcounty',
                        'facility'   => 'Facility',
                    ])
                    ->required()
                    ->live(),

                // Org assignment fields, shown based on org_level
                Forms\Components\Select::make('counties')
                    ->label('Counties (for County Level)')
                    ->multiple()
                    ->options(County::all()->pluck('name', 'id'))
                    ->visible(fn ($get) => $get('org_level') === 'county')
                    ->required(fn ($get) => $get('org_level') === 'county'),

                Forms\Components\Select::make('subcounties')
                    ->label('Subcounties (for Subcounty Level)')
                    ->multiple()
                    ->options(Subcounty::all()->pluck('name', 'id'))
                    ->visible(fn ($get) => $get('org_level') === 'subcounty')
                    ->required(fn ($get) => $get('org_level') === 'subcounty'),

                Forms\Components\Select::make('facilities')
                    ->label('Facilities (for Facility Level)')
                    ->multiple()
                    ->options(Facility::all()->pluck('name', 'id'))
                    ->visible(fn ($get) => $get('org_level') === 'facility')
                    ->required(fn ($get) => $get('org_level') === 'facility'),

                Forms\Components\Select::make('facility_id')
                    ->label('Home Facility (optional)')
                    ->options(Facility::all()->pluck('name', 'id'))
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Display Name')->sortable()->searchable(['first_name','middle_name','last_name','id_number','phone','email']),
                Tables\Columns\TextColumn::make('email')->label('Email'),
                Tables\Columns\TextColumn::make('phone')->label('Phone'),
                Tables\Columns\TextColumn::make('cadre.name')->label('Cadre'),
                Tables\Columns\TextColumn::make('status')->label('Status'),
                Tables\Columns\TextColumn::make('roles')->formatStateUsing(
                    fn ($record) => implode(', ', $record->getRoleNames()->toArray())
                ),
                Tables\Columns\TextColumn::make('created_at')->date(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    // Hooks to sync pivot assignments after save
    public static function afterSave($record, $data)
    {
        if (isset($data['roles'])) {
            $record->syncRoles($data['roles']);
        }

        // Detach then attach for org level assignments
        $record->counties()->sync($data['counties'] ?? []);
        $record->subcounties()->sync($data['subcounties'] ?? []);
        $record->facilities()->sync($data['facilities'] ?? []);
    }
}
