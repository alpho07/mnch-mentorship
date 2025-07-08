<?php
namespace App\Filament\Resources\KnowledgeBaseArticleResource\Pages;

use App\Filament\Resources\KnowledgeBaseArticleResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKnowledgeBaseArticle extends ViewRecord
{
    protected static string $resource = KnowledgeBaseArticleResource::class;

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return $this->record->title;
    }

    protected function getViewData(): array
    {
        return [
            'article' => $this->record->load(['attachments', 'programs', 'category', 'tags', 'author']),
        ];
    }
}
