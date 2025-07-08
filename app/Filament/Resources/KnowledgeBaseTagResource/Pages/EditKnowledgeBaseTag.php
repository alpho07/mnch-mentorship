<?php

namespace App\Filament\Resources\KnowledgeBaseTagResource\Pages;

use App\Filament\Resources\KnowledgeBaseTagResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKnowledgeBaseTag extends EditRecord
{
    protected static string $resource = KnowledgeBaseTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
