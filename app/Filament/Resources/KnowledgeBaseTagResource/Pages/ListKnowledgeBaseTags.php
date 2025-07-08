<?php

namespace App\Filament\Resources\KnowledgeBaseTagResource\Pages;

use App\Filament\Resources\KnowledgeBaseTagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKnowledgeBaseTags extends ListRecords
{
    protected static string $resource = KnowledgeBaseTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
