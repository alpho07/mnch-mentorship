<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Remove 'roles', 'counties', 'subcounties', 'facilities' from $data to avoid mass assignment errors
        $roles = $data['roles'] ?? [];
        $counties = $data['counties'] ?? [];
        $subcounties = $data['subcounties'] ?? [];
        $facilities = $data['facilities'] ?? [];

        unset($data['roles'], $data['counties'], $data['subcounties'], $data['facilities']);

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
}
