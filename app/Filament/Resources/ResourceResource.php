<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResourceResource\Pages;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\ResourceType;
use App\Models\Tag;
use App\Models\AccessGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource as FilamentResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;

class ResourceResource extends FilamentResource
{
    protected static ?string $model = Resource::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title'; 

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information') 
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $context, $state, Forms\Set $set) =>
                                $context === 'create' ? $set('slug', Str::slug($state)) : null
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Resource::class, 'slug', ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\Textarea::make('excerpt')
                            ->maxLength(500)
                            ->rows(3)
                            ->hint('Brief description for listings'),

                        Forms\Components\RichEditor::make('content')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                                'codeBlock',
                                'undo',
                                'redo'
                            ]),

                        Forms\Components\TextInput::make('meta_description')
                            ->maxLength(160)
                            ->hint('SEO meta description (160 chars max)'),
                    ])
                    ->columns(2),  

                Forms\Components\Section::make('Classification')
                    ->schema([
                        Forms\Components\Select::make('resource_type_id')
                            ->relationship('resourceType', 'name')
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\Textarea::make('description'),
                                Forms\Components\TextInput::make('icon')
                                    ->hint('Heroicon name'),
                                Forms\Components\ColorPicker::make('color')
                                    ->default('#3B82F6'),
                            ]),

                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name', fn (Builder $query) =>
                                $query->active()->orderBy('name')
                            )
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\Textarea::make('description'),
                                Forms\Components\Select::make('parent_id')
                                    ->relationship('parent', 'name')
                                    ->searchable(),
                            ]),

                        Forms\Components\Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\ColorPicker::make('color')
                                    ->default('#6B7280'),
                            ]),

                        Forms\Components\Select::make('difficulty_level')
                            ->options([
                                'beginner' => 'Beginner',
                                'intermediate' => 'Intermediate',
                                'advanced' => 'Advanced',
                            ])
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media & Files')
                    ->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->image()
                            ->directory('resources/featured')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ]),

                        Forms\Components\FileUpload::make('file_path')
                            ->directory('resources/files')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-powerpoint',
                                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'video/*',
                                'audio/*',
                                'image/*',
                            ])
                            ->maxSize(100000) // 100MB
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $set('is_downloadable', true);
                                }
                            }),

                        Forms\Components\TextInput::make('external_url')
                            ->url()
                            ->hint('External link if no file upload'),

                        Forms\Components\TextInput::make('duration')
                            ->numeric()
                            ->suffix('seconds')
                            ->hint('For video/audio content'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Publishing')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->required()
                            ->default('draft')
                            ->live(),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'published')
                            ->default(now()),

                        Forms\Components\Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'authenticated' => 'Authenticated Users Only',
                                'restricted' => 'Restricted Access',
                            ])
                            ->required()
                            ->default('public')
                            ->live(),

                        Forms\Components\Select::make('author_id')
                            ->relationship('author', 'first_name')
                            ->required()
                            ->default(auth()->id())
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Resource'),

                        Forms\Components\Toggle::make('is_downloadable')
                            ->label('Allow Downloads'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Access Control')
                    ->schema([
                        Forms\Components\Select::make('accessGroups')
                            ->relationship('accessGroups', 'name')
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('visibility') === 'restricted'),

                        Forms\Components\Select::make('scopedFacilities')
                            ->relationship('scopedFacilities', 'name')
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('visibility') === 'restricted'),

                        Forms\Components\Select::make('scopedCounties')
                            ->relationship('scopedCounties', 'name')
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('visibility') === 'restricted'),

                        Forms\Components\Select::make('scopedDepartments')
                            ->relationship('scopedDepartments', 'name')
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get) => $get('visibility') === 'restricted'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('visibility') === 'restricted')
                    ->columns(2),

                Forms\Components\Section::make('Learning Objectives')
                    ->schema([
                        Forms\Components\TagsInput::make('prerequisites')
                            ->hint('What users should know before accessing this resource'),

                        Forms\Components\TagsInput::make('learning_outcomes')
                            ->hint('What users will learn from this resource'),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-resource.png')),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\BadgeColumn::make('resourceType.name')
                    ->label('Type')
                    ->colors([
                        'primary' => 'PDF',
                        'success' => 'Video',
                        'warning' => 'Audio',
                        'danger' => 'Document',
                    ]),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'success' => 'published',
                        'danger' => 'archived',
                    ]),

                Tables\Columns\BadgeColumn::make('visibility')
                    ->colors([
                        'success' => 'public',
                        'warning' => 'authenticated',
                        'danger' => 'restricted',
                    ]),

                Tables\Columns\TextColumn::make('author.full_name')
                    ->label('Author')
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('download_count')
                    ->label('Downloads')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('like_count')
                    ->label('Likes')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('resource_type_id')
                    ->relationship('resourceType', 'name')
                    ->label('Type')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category')
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'authenticated' => 'Authenticated',
                        'restricted' => 'Restricted',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('is_featured')
                    ->query(fn (Builder $query): Builder => $query->where('is_featured', true))
                    ->label('Featured Only'),

                Tables\Filters\Filter::make('has_file')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('file_path'))
                    ->label('Has File'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Resource $record): string => route('resources.show', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each(fn ($record) =>
                            $record->update([
                                'status' => 'published',
                                'published_at' => now()
                            ])
                        ))
                        ->requiresConfirmation()
                        ->color('success'),
                    Tables\Actions\BulkAction::make('feature')
                        ->label('Mark as Featured')
                        ->icon('heroicon-o-star')
                        ->action(fn ($records) => $records->each(fn ($record) =>
                            $record->update(['is_featured' => true])
                        ))
                        ->color('warning'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Resource Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('title'),
                        Infolists\Components\TextEntry::make('slug'),
                        Infolists\Components\TextEntry::make('excerpt'),
                        Infolists\Components\TextEntry::make('resourceType.name')
                            ->label('Type'),
                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Category'),
                        Infolists\Components\TextEntry::make('author.full_name')
                            ->label('Author'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('view_count')
                            ->label('Views'),
                        Infolists\Components\TextEntry::make('download_count')
                            ->label('Downloads'),
                        Infolists\Components\TextEntry::make('like_count')
                            ->label('Likes'),
                        Infolists\Components\TextEntry::make('dislike_count')
                            ->label('Dislikes'),
                        Infolists\Components\TextEntry::make('comment_count')
                            ->label('Comments'),
                        Infolists\Components\TextEntry::make('read_time')
                            ->label('Est. Read Time')
                            ->suffix(' min'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResources::route('/'),
            'create' => Pages\CreateResource::route('/create'),
            //'view' => Pages\ViewResource::route('/{record}'),
            'edit' => Pages\EditResource::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
