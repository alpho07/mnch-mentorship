<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord {

    protected static string $resource = UserResource::class;

    /**
     * Store the auto-generated password so we can display it after creation.
     */
    protected ?string $generatedPassword = null;

    protected function handleRecordCreation(array $data): Model {
        // Remove pivot data to avoid mass assignment errors
        $roles = $data['roles'] ?? [];
        $counties = $data['counties'] ?? [];
        $subcounties = $data['subcounties'] ?? [];
        $facilities = $data['facilities'] ?? [];

        unset($data['roles'], $data['counties'], $data['subcounties'], $data['facilities']);

        // Auto-generate an 8-character password
        $this->generatedPassword = Str::random(8);
        $data['password'] = $this->generatedPassword;

        // Ensure display name is built from first, middle, last names
        $parts = array_filter([
            trim($data['first_name'] ?? ''),
            trim($data['middle_name'] ?? ''),
            trim($data['last_name'] ?? ''),
        ]);
        $data['name'] = implode(' ', $parts);

        /** @var \App\Models\User $user */
        $user = static::getModel()::create($data);

        // Sync roles
        if (!empty($roles)) {
            $user->syncRoles($roles);
        }

        // Sync organization units
        $user->counties()->sync($counties);
        $user->subcounties()->sync($subcounties);
        $user->facilities()->sync($facilities);

        return $user;
    }

    protected function afterCreate(): void {
        // Show the auto-generated password to the admin
        Notification::make()
                ->title('User Created Successfully')
                ->body("Auto-generated password for **{$this->record->name}**: `{$this->generatedPassword}`\n\nPlease copy and share this password with the user securely. It will not be shown again.")
                ->success()
                ->persistent()
                ->send();
    }
}
