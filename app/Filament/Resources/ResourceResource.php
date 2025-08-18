<?php

namespace App\Filament\Resources;

use App\Models\Resource;
use App\Models\Tag;
use App\Models\ResourceCategory;
use App\Models\ResourceType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource as FilamentResource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Pages\SubNavigationPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\ResourceResource\Pages;


class ResourceResource extends FilamentResource
{
    protected static ?string $model = Resource::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 1;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Resource Details')
                ->tabs([
                    // === BASIC INFORMATION TAB ===
                    Forms\Components\Tabs\Tab::make('Basic Information')
                        ->icon('heroicon-m-information-circle')
                        ->schema([
                            Forms\Components\Section::make('Content Details')
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                                            $operation === 'create' ? $set('slug', Str::slug($state)) : null
                                        )
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('slug')
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true)
                                        ->rules(['alpha_dash'])
                                        ->helperText('Used in URLs. Will be auto-generated from title if left empty.')
                                        ->columnSpan(1),

                                    Forms\Components\Textarea::make('excerpt')
                                        ->maxLength(500)
                                        ->rows(3)
                                        ->helperText('Brief description shown in listings')
                                        ->columnSpanFull(),

                                    Forms\Components\RichEditor::make('content')
                                        ->required()
                                        ->toolbarButtons([
                                            'attachFiles',
                                            'blockquote',
                                            'bold',
                                            'bulletList',
                                            'codeBlock',
                                            'h2',
                                            'h3',
                                            'italic',
                                            'link',
                                            'orderedList',
                                            'redo',
                                            'strike',
                                            'underline',
                                            'undo',
                                        ])
                                        ->columnSpanFull(),

                                    Forms\Components\Textarea::make('meta_description')
                                        ->maxLength(160)
                                        ->rows(2)
                                        ->helperText('SEO meta description (160 characters max)')
                                        ->columnSpanFull(),
                                ])->columns(3),
                        ]),

                    // === CATEGORIZATION TAB ===
                    Forms\Components\Tabs\Tab::make('Categorization')
                        ->icon('heroicon-m-folder')
                        ->schema([
                            Forms\Components\Section::make('Classification')
                                ->schema([
                                    Forms\Components\Select::make('category_id')
                                        ->label('Category')
                                        ->relationship('category', 'name')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('name')
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('slug')
                                                ->required()
                                                ->maxLength(255)
                                                ->unique(),
                                            Forms\Components\Textarea::make('description')
                                                ->maxLength(500),
                                        ]),

                                    Forms\Components\Select::make('resource_type_id')
                                        ->label('Resource Type')
                                        ->relationship('resourceType', 'name')
                                        ->required()
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            Forms\Components\TextInput::make('name')
                                                ->required()
                                                ->maxLength(255),
                                            Forms\Components\TextInput::make('slug')
                                                ->required()
                                                ->maxLength(255)
                                                ->unique(),
                                            Forms\Components\TextInput::make('icon')
                                                ->helperText('FontAwesome icon class (e.g., fas fa-file-pdf)'),
                                        ]),

                                    Forms\Components\Select::make('difficulty_level')
                                        ->options([
                                            'beginner' => 'Beginner',
                                            'intermediate' => 'Intermediate',
                                            'advanced' => 'Advanced',
                                        ])
                                        ->native(false),

                                    Forms\Components\Select::make('author_id')
                                        ->label('Author')
                                        ->relationship('author', 'first_name')
                                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                        ->searchable(['first_name', 'last_name', 'email'])
                                        ->preload()
                                        ->default(fn () => auth()->id()),
                                ])->columns(2),

                            Forms\Components\Section::make('Tags')
                                ->schema([
                                    Forms\Components\TagsInput::make('tag_names')
                                        ->label('Tags')
                                        ->suggestions(Tag::pluck('name')->toArray())
                                        ->nestedRecursiveRules([
                                            'min:1',
                                            'max:50',
                                        ])
                                        ->helperText('Enter tags and press enter to add them')
                                        ->columnSpanFull(),
                                ]),

                            Forms\Components\Section::make('Learning Details')
                                ->schema([
                                    Forms\Components\Repeater::make('prerequisites')
                                        ->schema([
                                            Forms\Components\TextInput::make('prerequisite')
                                                ->required()
                                                ->maxLength(255),
                                        ])
                                        ->itemLabel(fn (array $state): ?string => $state['prerequisite'] ?? null)
                                        ->addActionLabel('Add Prerequisite')
                                        ->collapsible()
                                        ->columnSpan(1),

                                    Forms\Components\Repeater::make('learning_outcomes')
                                        ->schema([
                                            Forms\Components\TextInput::make('outcome')
                                                ->required()
                                                ->maxLength(255),
                                        ])
                                        ->itemLabel(fn (array $state): ?string => $state['outcome'] ?? null)
                                        ->addActionLabel('Add Learning Outcome')
                                        ->collapsible()
                                        ->columnSpan(1),
                                ])->columns(2),
                        ]),

                    // === FILES & MEDIA TAB ===
                    Forms\Components\Tabs\Tab::make('Files & Media')
                        ->icon('heroicon-m-cloud-arrow-up')
                        ->schema([
                            Forms\Components\Section::make('Featured Image')
                                ->schema([
                                    Forms\Components\FileUpload::make('featured_image')
                                        ->label('Featured Image')
                                        ->image()
                                        ->disk('thumbnails')
                                        ->directory('resources')
                                        ->visibility('public')
                                        ->maxSize(5120) // 5MB
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                                        ->imageEditor()
                                        ->imageEditorAspectRatios([
                                            '16:9',
                                            '4:3',
                                            '1:1',
                                        ])
                                        ->imageResizeMode('cover')
                                        ->imageCropAspectRatio('16:9')
                                        ->imageResizeTargetWidth('800')
                                        ->imageResizeTargetHeight('450')
                                        //->optimize('webp')
                                        ->getUploadedFileNameForStorageUsing(
                                            fn (TemporaryUploadedFile $file): string => 
                                                'featured-' . time() . '-' . Str::random(8) . '.' . $file->getClientOriginalExtension()
                                        )
                                        ->helperText('Recommended size: 800x450px. Will be automatically optimized.')
                                        ->columnSpanFull(),
                                ])->collapsible(),

                            Forms\Components\Section::make('Resource File')
                                ->schema([
                                    Forms\Components\FileUpload::make('file_path')
                                        ->label('Main Resource File')
                                        ->disk('resources')
                                        ->directory(fn () => 'documents/' . date('Y/m'))
                                        ->visibility('private')
                                        ->maxSize(51200) // 50MB
                                        ->acceptedFileTypes([
                                            'application/pdf',
                                            'application/msword',
                                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                            'application/vnd.ms-powerpoint',
                                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                            'application/vnd.ms-excel',
                                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                            'text/plain',
                                            'text/csv',
                                            'application/zip',
                                            'application/x-rar-compressed',
                                            'video/mp4',
                                            'video/mpeg',
                                            'audio/mpeg',
                                            'audio/wav',
                                            'image/jpeg',
                                            'image/png',
                                        ])
                                        ->getUploadedFileNameForStorageUsing(
                                            fn (TemporaryUploadedFile $file): string => 
                                                date('Y/m/') . hash('sha256', $file->getClientOriginalName() . time() . Str::random(8)) . '.' . $file->getClientOriginalExtension()
                                        )
                                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                                            if ($state) {
                                                $file = is_array($state) ? $state[0] : $state;
                                                if ($file instanceof TemporaryUploadedFile) {
                                                    $set('file_size', $file->getSize());
                                                    $set('file_type', $file->getMimeType());
                                                    $set('is_downloadable', true);
                                                    
                                                    // Auto-detect resource type based on file
                                                    $mimeType = $file->getMimeType();
                                                    $resourceType = static::getResourceTypeFromMime($mimeType);
                                                    if ($resourceType) {
                                                        $set('resource_type_id', $resourceType->id);
                                                    }
                                                }
                                            }
                                        })
                                        ->helperText('Maximum file size: 50MB. Supported formats: PDF, Word, PowerPoint, Excel, Text, Archives, Videos, Audio, Images')
                                        ->columnSpan(2),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('file_size')
                                                ->label('File Size (bytes)')
                                                ->numeric()
                                                ->readOnly()
                                                ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' bytes' : null),

                                            Forms\Components\TextInput::make('file_type')
                                                ->label('MIME Type')
                                                ->readOnly(),
                                        ]),

                                    Forms\Components\TextInput::make('duration')
                                        ->label('Duration')
                                        ->helperText('For video/audio files (e.g., "5:30" or "1h 20m")')
                                        ->maxLength(50),
                                ])->collapsible(),

                            Forms\Components\Section::make('External Resource')
                                ->schema([
                                    Forms\Components\TextInput::make('external_url')
                                        ->label('External URL')
                                        ->url()
                                        ->maxLength(500)
                                        ->helperText('If the resource is hosted on an external platform (YouTube, Google Drive, etc.)')
                                        ->columnSpanFull(),
                                ])->collapsible(),
                        ]),

                    // === SETTINGS & PUBLISHING TAB ===
                    Forms\Components\Tabs\Tab::make('Settings & Publishing')
                        ->icon('heroicon-m-cog-6-tooth')
                        ->schema([
                            Forms\Components\Section::make('Publication Settings')
                                ->schema([
                                    Forms\Components\Select::make('status')
                                        ->options([
                                            'draft' => 'Draft',
                                            'published' => 'Published',
                                            'archived' => 'Archived',
                                        ])
                                        ->default('draft')
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('visibility')
                                        ->options([
                                            'public' => 'Public - Everyone can access',
                                            'authenticated' => 'Authenticated Users Only',
                                            'private' => 'Private - Restricted Access',
                                        ])
                                        ->default('public')
                                        ->required()
                                        ->native(false)
                                        ->helperText('Controls who can view this resource'),

                                    Forms\Components\DateTimePicker::make('published_at')
                                        ->label('Publish Date')
                                        ->default(now())
                                        ->native(false),
                                ])->columns(3),

                            Forms\Components\Section::make('Resource Features')
                                ->schema([
                                    Forms\Components\Toggle::make('is_featured')
                                        ->label('Featured Resource')
                                        ->helperText('Featured resources appear in special sections'),

                                    Forms\Components\Toggle::make('is_downloadable')
                                        ->label('Allow Downloads')
                                        ->default(true)
                                        ->helperText('Users can download the attached file'),

                                    Forms\Components\TextInput::make('sort_order')
                                        ->label('Sort Order')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('Higher numbers appear first'),
                                ])->columns(3),

                            Forms\Components\Section::make('Access Control')
                                ->schema([
                                    Forms\Components\Select::make('access_groups')
                                        ->label('Access Groups')
                                        ->multiple()
                                        ->relationship('accessGroups', 'name')
                                        ->preload()
                                        ->helperText('Restrict access to specific user groups'),

                                    Forms\Components\Select::make('scoped_facilities')
                                        ->label('Facility Access')
                                        ->multiple()
                                        ->relationship('scopedFacilities', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->helperText('Limit access to specific facilities'),

                                    Forms\Components\Select::make('scoped_departments')
                                        ->label('Department Access')
                                        ->multiple()
                                        ->relationship('scopedDepartments', 'name')
                                        ->preload()
                                        ->helperText('Restrict to specific departments'),
                                ])->columns(3)->collapsible(),
                        ]),

                    // === ANALYTICS TAB ===
                    Forms\Components\Tabs\Tab::make('Analytics')
                        ->icon('heroicon-m-chart-bar')
                        ->schema([
                            Forms\Components\Section::make('Engagement Metrics')
                                ->schema([
                                    Forms\Components\Grid::make(4)
                                        ->schema([
                                            Forms\Components\Placeholder::make('view_count')
                                                ->label('Total Views')
                                                ->content(fn (?Resource $record) => $record?->view_count ?? 0),

                                            Forms\Components\Placeholder::make('download_count')
                                                ->label('Downloads')
                                                ->content(fn (?Resource $record) => $record?->download_count ?? 0),

                                            Forms\Components\Placeholder::make('like_count')
                                                ->label('Likes')
                                                ->content(fn (?Resource $record) => $record?->like_count ?? 0),

                                            Forms\Components\Placeholder::make('comment_count')
                                                ->label('Comments')
                                                ->content(fn (?Resource $record) => $record?->comments()->approved()->count() ?? 0),
                                        ]),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Placeholder::make('created_at')
                                                ->label('Created')
                                                ->content(fn (?Resource $record) => $record?->created_at?->format('M j, Y g:i A') ?? 'Not saved yet'),

                                            Forms\Components\Placeholder::make('updated_at')
                                                ->label('Last Updated')
                                                ->content(fn (?Resource $record) => $record?->updated_at?->format('M j, Y g:i A') ?? 'Not saved yet'),
                                        ]),
                                ])->hiddenOn('create'),
                        ])->hiddenOn('create'),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->disk('thumbnails')
                    ->size(60)
                    ->square()
                    ->defaultImageUrl(url('/images/placeholder-resource.png')),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('medium')
                    ->description(fn (Resource $record): string => Str::limit($record->excerpt, 50)),

                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->searchable()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('resourceType.name')
                    ->label('Type')
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('has_file')
                    ->label('File')
                    ->getStateUsing(fn (Resource $record) => !empty($record->file_path))
                    ->boolean()
                    ->trueIcon('heroicon-o-document')
                    ->falseIcon('heroicon-o-link')
                    ->trueColor('success')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Size')
                    ->getStateUsing(fn (Resource $record) => $record->formatted_file_size)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'archived' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                   /* ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'authenticated' => 'info',
                        'private' => 'warning',
                    })*/,

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make(),
                    ]),

                Tables\Columns\TextColumn::make('download_count')
                    ->label('Downloads')
                    ->numeric()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make(),
                    ]),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Not published'),

                Tables\Columns\TextColumn::make('author.full_name')
                    ->label('Author')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
            ])
            ->filters([
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
                        'private' => 'Private',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('resource_type')
                    ->relationship('resourceType', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured')
                    ->placeholder('All resources')
                    ->trueLabel('Featured only')
                    ->falseLabel('Not featured'),

                Tables\Filters\TernaryFilter::make('is_downloadable')
                    ->label('Downloadable')
                    ->placeholder('All resources')
                    ->trueLabel('Downloadable only')
                    ->falseLabel('Not downloadable'),

                Tables\Filters\TernaryFilter::make('has_file')
                    ->label('Has File')
                    ->placeholder('All resources')
                    ->trueLabel('With files')
                    ->falseLabel('Without files')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('file_path'),
                        false: fn (Builder $query) => $query->whereNull('file_path'),
                    ),

                Tables\Filters\Filter::make('published_date')
                    ->form([
                        Forms\Components\DatePicker::make('published_from')
                            ->label('Published from'),
                        Forms\Components\DatePicker::make('published_until')
                            ->label('Published until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['published_from'], fn (Builder $query, $date): Builder => $query->whereDate('published_at', '>=', $date))
                            ->when($data['published_until'], fn (Builder $query, $date): Builder => $query->whereDate('published_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->url(fn (Resource $record): string => route('resources.show', $record->slug))
                        ->openUrlInNewTab(),

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (Resource $record): string => route('admin.resources.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Resource $record): bool => !empty($record->file_path) && Storage::disk('resources')->exists($record->file_path)),

                    Tables\Actions\Action::make('preview')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Resource $record): string => route('admin.resources.preview', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Resource $record): bool => $record->isPreviewable()),

                    Tables\Actions\ReplicateAction::make()
                        ->excludeAttributes(['slug', 'view_count', 'download_count', 'like_count'])
                        ->beforeReplicaSaved(function (Resource $replica, array $data): void {
                            $replica->title = $data['title'] . ' (Copy)';
                            $replica->slug = Str::slug($replica->title);
                            $replica->status = 'draft';
                            $replica->published_at = null;
                        }),

                    Tables\Actions\DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(function (Resource $record) {
                                $record->update([
                                    'status' => 'published',
                                    'published_at' => $record->published_at ?? now(),
                                ]);
                            });
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('draft')
                        ->label('Mark as Draft')
                        ->icon('heroicon-o-pencil')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'draft']))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('feature')
                        ->label('Feature Selected')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_featured' => true]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }

    public static function getRelations(): array
    {
        return [
            // You can add relation managers here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResources::route('/'),
            'create' => Pages\CreateResource::route('/create'),
            'edit' => Pages\EditResource::route('/{record}/edit'),
            'view' => Pages\ViewResource::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // === HELPER METHODS ===

    protected static function getResourceTypeFromMime(string $mimeType): ?ResourceType
    {
        $typeMapping = [
            'application/pdf' => 'pdf',
            'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-powerpoint' => 'presentation',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'presentation',
            'application/vnd.ms-excel' => 'spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'spreadsheet',
            'video/mp4' => 'video',
            'video/mpeg' => 'video',
            'audio/mpeg' => 'audio',
            'audio/wav' => 'audio',
            'application/zip' => 'archive',
            'text/plain' => 'text',
            'text/csv' => 'text',
        ];

        foreach ($typeMapping as $mime => $typeName) {
            if ($mimeType === $mime) {
                return ResourceType::where('name', 'like', "%{$typeName}%")->first();
            }
        }

        return null;
    }

    // === FORM DATA MUTATIONS ===

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::handleFormDataMutations($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::handleFormDataMutations($data);
    }

    protected static function handleFormDataMutations(array $data): array
    {
        // Handle tag names conversion
        if (isset($data['tag_names']) && is_array($data['tag_names'])) {
            $tagIds = [];
            foreach ($data['tag_names'] as $tagName) {
                if (!empty($tagName)) {
                    $tag = Tag::firstOrCreate(
                        ['name' => $tagName],
                        ['slug' => Str::slug($tagName)]
                    );
                    $tagIds[] = $tag->id;
                }
            }
            $data['tag_ids'] = $tagIds;
            unset($data['tag_names']);
        }

        // Handle prerequisites format
        if (isset($data['prerequisites']) && is_array($data['prerequisites'])) {
            $data['prerequisites'] = array_column($data['prerequisites'], 'prerequisite');
        }

        // Handle learning outcomes format
        if (isset($data['learning_outcomes']) && is_array($data['learning_outcomes'])) {
            $data['learning_outcomes'] = array_column($data['learning_outcomes'], 'outcome');
        }

        // Auto-generate slug if not provided
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Set author if not provided
        if (empty($data['author_id'])) {
            $data['author_id'] = auth()->id();
        }

        return $data;
    }

    // === RESOURCE LIFECYCLE HOOKS ===

    public static function afterCreate(Resource $record, array $data): void
    {
        // Sync tags after creation
        if (isset($data['tag_ids'])) {
            $record->tags()->sync($data['tag_ids']);
        }

        // Sync access groups
        if (isset($data['access_groups'])) {
            $record->accessGroups()->sync($data['access_groups']);
        }

        // Sync scoped facilities
        if (isset($data['scoped_facilities'])) {
            $record->scopedFacilities()->sync($data['scoped_facilities']);
        }

        // Sync scoped departments
        if (isset($data['scoped_departments'])) {
            $record->scopedDepartments()->sync($data['scoped_departments']);
        }
    }

    public static function afterSave(Resource $record, array $data): void
    {
        // Sync relationships after save
        static::afterCreate($record, $data);
    }

    // === CUSTOM FORM COMPONENTS ===

    public static function getFileUploadSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('File Upload')
            ->schema([
                Forms\Components\FileUpload::make('file_path')
                    ->label('Resource File')
                    ->disk('resources')
                    ->directory(fn () => 'documents/' . date('Y/m'))
                    ->visibility('private')
                    ->maxSize(51200) // 50MB
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                        'text/csv',
                        'application/zip',
                        'video/mp4',
                        'audio/mpeg',
                    ])
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => 
                            date('Y/m/') . hash('sha256', $file->getClientOriginalName() . time()) . '.' . $file->getClientOriginalExtension()
                    )
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $file = is_array($state) ? $state[0] : $state;
                            if ($file instanceof TemporaryUploadedFile) {
                                $set('file_size', $file->getSize());
                                $set('file_type', $file->getMimeType());
                                $set('is_downloadable', true);
                            }
                        }
                    })
                    ->columnSpanFull(),
            ]);
    }

    // === GLOBAL SEARCH ===

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'title',
            'excerpt',
            'category.name',
            'resourceType.name',
            'author.first_name',
            'author.last_name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->title;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Category' => $record->category?->name,
            'Type' => $record->resourceType?->name,
            'Author' => $record->author?->full_name,
            'Status' => ucfirst($record->status),
        ];
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('view')
                ->label('View Resource')
                ->url(route('resources.show', $record->slug))
                ->openUrlInNewTab(),
            Action::make('edit')
                ->label('Edit')
                ->url(static::getUrl('edit', ['record' => $record])),
        ];
    }

    // === NAVIGATION BADGE ===

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'draft')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}


