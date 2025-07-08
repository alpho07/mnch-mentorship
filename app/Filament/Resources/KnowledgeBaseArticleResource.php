<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KnowledgeBaseArticleResource\Pages;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseTag;
use App\Models\KnowledgeBaseAttachment;
use App\Models\Program;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeBaseArticleResource extends Resource
{
    protected static ?string $model = KnowledgeBaseArticle::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Knowledge Base';
    protected static ?string $label = 'Knowledge Base Article';
    protected static ?string $pluralLabel = 'Knowledge Base Articles';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(255),
            Forms\Components\Select::make('programs')
                ->label('Programs')
                ->multiple()
                ->relationship('programs', 'name')
                ->required()
                ->preload(),
            Forms\Components\Select::make('category_id')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\Select::make('tags')
                ->multiple()
                ->relationship('tags', 'name')
                ->searchable()
                ->preload()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\ColorPicker::make('color'),
                ]),
            Forms\Components\RichEditor::make('content')
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'underline',
                    'strike',
                    'bulletList',
                    'orderedList',
                    'link',
                    'blockquote',
                    'h1',
                    'h2',
                    'h3',
                    'h4',
                    'h5',
                    'h6',
                    'codeBlock',
                    'image',
                    'video',
                    'undo',
                    'redo'
                ])
                ->label('Article Body')
                ->columnSpanFull(),
            Forms\Components\Repeater::make('attachments')
                ->label('Attachments')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options([
                            'pdf'   => 'PDF Document',
                            'video' => 'Video (File or Link)',
                            'image' => 'Image',
                            'link'  => 'External Link',
                        ])
                        ->required(),
                    Forms\Components\FileUpload::make('file_path')
                        ->label('Upload File')
                        ->visible(fn(Get $get) => in_array($get('type'), ['pdf', 'image', 'video'])),
                    Forms\Components\TextInput::make('external_url')
                        ->label('External URL (YouTube, Vimeo, etc.)')
                        ->visible(fn(Get $get) => in_array($get('type'), ['video', 'link'])),
                    Forms\Components\TextInput::make('display_name')->required(),
                ]),
            Forms\Components\Toggle::make('is_published')->default(true)->label('Published'),
        ])->columns(2);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->wrap(),
                Tables\Columns\TagsColumn::make('programs.name')->label('Programs'),
                Tables\Columns\TextColumn::make('category.name')->badge(),
                Tables\Columns\TagsColumn::make('tags.name')->colors(fn($record) => $record->tags->pluck('color')->toArray()),
                Tables\Columns\IconColumn::make('is_published')->boolean()->label('Published'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('programs')->relationship('programs', 'name')->multiple(),
                Tables\Filters\SelectFilter::make('category_id')->relationship('category', 'name'),
                Tables\Filters\SelectFilter::make('tags')->relationship('tags', 'name')->multiple(),
                Tables\Filters\TernaryFilter::make('is_published')->label('Published'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Preview')->color('info'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKnowledgeBaseArticles::route('/'),
            'create' => Pages\CreateKnowledgeBaseArticle::route('/create'),
            'edit' => Pages\EditKnowledgeBaseArticle::route('/{record}/edit'),
            'view' => Pages\ViewKnowledgeBaseArticle::route('/{record}'),
            //'resources' => Pages\KnowledgeBasePortal::route('/{resources}'),
        ];
    }
}
