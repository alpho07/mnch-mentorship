<?php

namespace App\Filament\Resources\ProgramModuleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ContentsRelationManager extends RelationManager
{
    protected static string $relationship = 'contents';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label('Content Type')
                ->options([
                    'introduction' => 'Introduction Notes',
                    'video' => 'Video',
                    'case_scenario' => 'Case Scenario',
                ])
                ->required()
                ->live()
                ->native(false),

            Forms\Components\TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\RichEditor::make('content')
                ->label('Content')
                ->required(fn (Forms\Get $get): bool => in_array($get('type'), ['introduction', 'case_scenario'], true))
                ->visible(fn (Forms\Get $get): bool => in_array($get('type'), ['introduction', 'case_scenario'], true))
                ->columnSpanFull(),

            Forms\Components\TextInput::make('video_url')
                ->label('Video URL')
                ->url()
                ->prefixIcon('heroicon-m-link')
                ->live()
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'video')
                ->columnSpanFull(),

            Forms\Components\FileUpload::make('video_path')
                ->label('Video File')
                ->directory('program-module-videos')
                ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                ->maxSize(102400)
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'video')
                ->columnSpanFull(),

            Forms\Components\Placeholder::make('video_preview')
                ->label('Preview')
                ->content(function (Forms\Get $get): HtmlString {
                    $url = $get('video_url');

                    if (! $url) {
                        return new HtmlString('<span class="text-sm text-gray-500">Enter a YouTube URL to see a preview.</span>');
                    }

                    $patterns = [
                        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
                        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
                        '/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
                        '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
                    ];

                    $videoId = null;
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $url, $matches)) {
                            $videoId = $matches[1];
                            break;
                        }
                    }

                    if (! $videoId) {
                        return new HtmlString('<span class="text-sm text-gray-500">Preview available for YouTube URLs only.</span>');
                    }

                    return new HtmlString(sprintf(
                        '<div class="w-full rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700" style="height: 480px;">
                            <iframe src="https://www.youtube.com/embed/%s" title="YouTube video preview" style="width: 100%%; height: 100%%;" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </div>',
                        $videoId
                    ));
                })
                ->visible(fn (Forms\Get $get): bool => $get('type') === 'video')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('order_sequence')
                ->label('Order Sequence')
                ->numeric()
                ->default(0)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('order_sequence')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'introduction' => 'Introduction',
                        'video' => 'Video',
                        'case_scenario' => 'Case Scenario',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'introduction' => 'info',
                        'video' => 'danger',
                        'case_scenario' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('content')
                    ->label('Preview')
                    ->limit(50)
                    ->html()
                    ->wrap()
                    ->visible(fn (): bool => true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('order_sequence', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'introduction' => 'Introduction',
                        'video' => 'Video',
                        'case_scenario' => 'Case Scenario',
                    ])
                    ->native(false),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
