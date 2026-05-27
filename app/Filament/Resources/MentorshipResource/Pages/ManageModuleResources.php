<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Filament\Resources\ResourceResource;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use App\Models\Resource;
use App\Models\Training;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManageModuleResources extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MentorshipTrainingResource::class;
    protected static string $view = 'filament.pages.manage-module-resources';
    protected static bool $shouldRegisterNavigation = false;

    public Training $training;
    public MentorshipClass $class;
    public ClassModule $module;
    public ProgramModule $programModule;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void
    {
        $this->training = $training;
        $this->class = $class;
        $this->module = $module->load('programModule');
        $this->programModule = $module->programModule;
    }

    public function getTitle(): string
    {
        return "Resources — {$this->programModule->name}";
    }

    public function getSubheading(): ?string
    {
        $count = $this->programModule->resources()->count();
        return "{$count} resource(s) linked to this module";
    }

    private function isAdmin(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_modules')
                ->label('Back to Modules')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('class-modules', [
                    'training' => $this->training->id,
                    'class'    => $this->class->id,
                ])),

            Actions\Action::make('attach_resources')
                ->label('Attach Resources')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->visible(fn () => $this->isAdmin())
                ->slideOver()
                ->modalWidth('2xl')
                ->modalHeading('Attach Resources to Module')
                ->modalDescription("Search and attach existing resources to '{$this->programModule->name}'.")
                ->form([
                    \Filament\Forms\Components\Select::make('resource_ids')
                        ->label('Resources')
                        ->multiple()
                        ->options(function () {
                            $alreadyLinked = $this->programModule->resources()->pluck('resources.id')->toArray();
                            return Resource::whereNotIn('id', $alreadyLinked)
                                ->orderBy('title')
                                ->pluck('title', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->helperText('Resources already attached to this module are excluded.'),
                ])
                ->action(function (array $data) {
                    $ids = $data['resource_ids'] ?? [];
                    if (!empty($ids)) {
                        $this->programModule->resources()->syncWithoutDetaching($ids);
                        Notification::make()->success()->title(count($ids) . ' resource(s) attached')->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Resource::query()
                    ->whereHas('programModules', fn (Builder $q) => $q->where('program_module_id', $this->programModule->id))
                    ->withCount('files')
                    ->with('primaryFile')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Resource $record) => $record->excerpt
                        ? \Illuminate\Support\Str::limit($record->excerpt, 80)
                        : null),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('resourceType.name')
                    ->label('Type')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('files_count')
                    ->label('Files')
                    ->numeric()
                    ->color('primary')
                    ->icon('heroicon-o-document'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft'     => 'warning',
                        'published' => 'success',
                        'archived'  => 'danger',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('Not published'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview_file')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->iconButton()
                    ->tooltip('Preview file')
                    ->modalContent(fn (Resource $record) => view(
                        'filament.modals.resource-file-preview',
                        ['file' => $record->primaryFile]
                    ))
                    ->modalHeading(fn (Resource $record) => $record->title)
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn (Resource $record) => $record->primaryFile?->exists()),

                Tables\Actions\Action::make('download_file')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('Download file')
                    ->url(fn (Resource $record) => $record->primaryFile
                        ? route('admin.resource-files.download', $record->primaryFile)
                        : null)
                    ->visible(fn (Resource $record) => $record->primaryFile?->exists()),

                Tables\Actions\Action::make('edit_resource')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Edit Resource')
                    ->url(fn (Resource $record) => ResourceResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn () => $this->isAdmin()),

                Tables\Actions\Action::make('detach')
                    ->label('Detach')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Remove from this module')
                    ->requiresConfirmation()
                    ->modalHeading('Detach Resource')
                    ->modalDescription('This removes the resource from this module only — the resource itself is not deleted.')
                    ->visible(fn () => $this->isAdmin())
                    ->action(function (Resource $record) {
                        $this->programModule->resources()->detach($record->id);
                        Notification::make()->success()->title('Resource detached')->send();
                    }),
            ])
            ->emptyStateHeading('No Resources Attached')
            ->emptyStateDescription('Use "Attach Resources" above to link existing resources to this module.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('title');
    }
}
