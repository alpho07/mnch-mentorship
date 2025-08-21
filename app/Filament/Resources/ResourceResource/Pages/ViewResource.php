<?php
namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewResource extends ViewRecord
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('view_frontend')
                ->label('View on Frontend')
                ->icon('heroicon-o-globe-alt')
                ->url(fn () => route('resources.show', $this->getRecord()->slug))
                ->openUrlInNewTab(),

            Actions\Action::make('download')
                ->label('Download File')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => route('resources.download', $this->getRecord()->slug))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->canBeDownloaded()),

            Actions\Action::make('preview')
                ->label('Preview File')  
                ->icon('heroicon-o-eye')
                ->url(fn () => route('resources.preview', $this->getRecord()->slug))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->isPreviewable()),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Resource Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->size('lg')
                            ->weight('bold'),
                        
                        Infolists\Components\TextEntry::make('excerpt')
                            ->columnSpanFull(),
                        
                        Infolists\Components\TextEntry::make('category.name')
                            ->badge(),
                        
                        Infolists\Components\TextEntry::make('resourceType.name')
                            ->badge()
                            ->color('success'),
                        
                        Infolists\Components\TextEntry::make('difficulty_level')
                            ->badge()
                            ->color('warning'),
                        
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'warning',
                                'published' => 'success',
                                'archived' => 'danger',
                            }),
                    ])->columns(2),

                Infolists\Components\Section::make('File Information')
                    ->schema([
                        Infolists\Components\ImageEntry::make('featured_image')
                            ->disk('thumbnails')
                            ->height(200),
                        
                        Infolists\Components\TextEntry::make('formatted_file_size')
                            ->label('File Size'),
                        
                        Infolists\Components\TextEntry::make('file_type')
                            ->label('MIME Type'),
                        
                        Infolists\Components\IconEntry::make('is_downloadable')
                            ->label('Downloadable')
                            ->boolean(),
                    ])->columns(2),

                Infolists\Components\Section::make('Analytics')
                    ->schema([
                        Infolists\Components\TextEntry::make('view_count')
                            ->label('Views')
                            ->numeric(),
                        
                        Infolists\Components\TextEntry::make('download_count')
                            ->label('Downloads')
                            ->numeric(),
                        
                        Infolists\Components\TextEntry::make('like_count')
                            ->label('Likes')
                            ->numeric(),
                        
                        Infolists\Components\TextEntry::make('comment_count')
                            ->label('Comments')
                            ->numeric(),
                    ])->columns(4),

                Infolists\Components\Section::make('Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}