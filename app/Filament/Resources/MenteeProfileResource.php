<?php

// app/Filament/Resources/MenteeProfileResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\MenteeProfileResource\Pages;
use App\Filament\Resources\MenteeProfileResource\RelationManagers\TrainingParticipationsRelationManager;
use App\Models\MenteePlacementLog;
use App\Models\MenteeStatus;
use App\Models\MenteeStatusLog;
use App\Models\MenteeAssessmentResult;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Infolists\Components as Info;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Resources\RelationManagers\RelationGroup;

class MenteeProfileResource extends Resource {

    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Training Management';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Mentee Profiles';
    protected static ?int $navigationSort = 10;

    public static function getGloballySearchableAttributes(): array {
        return ['name', 'email', 'phone'];
    }

    public static function form(Form $form): Form {
        return $form; // managed via View page
    }

    public static function table(Table $table): Table {
        return $table
                        ->query(static::baseQuery())
                        ->columns([
                            Columns\TextColumn::make('name')
                            ->label('Mentee')
                            ->sortable()
                            ->searchable()
                            ->description(fn(User $record) => $record->email),
                            Columns\TextColumn::make('facility.name')
                            ->label('Facility')
                            ->sortable()
                            ->searchable(),
                            Columns\TextColumn::make('department.name')
                            ->label('Department')
                            ->sortable()
                            ->searchable(),
                            Columns\TextColumn::make('cadre.name')
                            ->label('Cadre')
                            ->badge()
                            ->sortable(),
                            Columns\TextColumn::make('trainings_count')
                            ->label('# Trainings')
                            ->counts('trainingParticipations')
                            ->sortable(),
                            // Overall Assessment: latest PASS/FAIL across all assessments
                            Columns\BadgeColumn::make('overall_assessment')
                            ->label('Overall Assessment')
                            ->state(fn(User $record) => static::latestAssessmentResult($record))
                            ->formatStateUsing(fn($state) => $state ? strtoupper($state) : '—')
                            ->colors([
                                'success' => 'pass',
                                'danger' => 'fail',
                                'gray' => fn($state) => blank($state),
                            ])
                            ->icons([
                                'heroicon-s-check-circle' => 'pass',
                                'heroicon-s-x-circle' => 'fail',
                            ]),
                            Columns\TextColumn::make('last_training_at')
                            ->label('Last Training')
                            ->state(fn(User $record) => static::computeLastTrainingDate($record))
                            ->date()
                            ->sortable(),
                            Columns\TextColumn::make('days_since_last')
                            ->label('Days Since')
                            ->state(fn(User $record) => static::computeDaysSinceLast($record))
                            ->suffix(fn($state) => is_numeric($state) ? ' days' : '')
                            ->sortable(),
                            Columns\BadgeColumn::make('current_status')
                            ->label('Current Status')
                            ->getStateUsing(fn(User $record) => static::currentStatus($record))
                            ->colors([
                                'success' => 'active',
                                'warning' => 'study_leave',
                                'danger' => fn($state) => in_array($state, static::attritionStatuses(), true),
                            ])
                            ->icons([
                                'heroicon-s-check-circle' => 'active',
                                'heroicon-s-clock' => 'study_leave',
                                'heroicon-s-exclamation-triangle' => fn($state) => in_array($state, static::attritionStatuses(), true),
                            ]),
                        ])
                        ->filters([
                            Filters\SelectFilter::make('facility_id')
                            ->label('Facility')
                            ->relationship('facility', 'name')
                            ->searchable(),
                            Filters\SelectFilter::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name')
                            ->searchable(),
                            Filters\SelectFilter::make('cadre_id')
                            ->label('Cadre')
                            ->relationship('cadre', 'name')
                            ->searchable(),
                            Filters\SelectFilter::make('status')
                            ->label('Current Status')
                            ->options(self::statusOptions())
                            ->query(function (Builder $q, array $data) {
                                if (!empty($data['value'])) {
                                    $status = $data['value'];
                                    $q->where(function ($sub) use ($status) {
                                        $sub->whereHas('statusLogs', function ($qq) use ($status) {
                                            $qq->orderByDesc('effective_date')->orderByDesc('id')
                                                    ->limit(1)->where('new_status', $status);
                                        })->orWhereDoesntHave('statusLogs');
                                    });
                                }
                            }),
                            // FIXED: time-window filters using EXISTS subqueries (robust, no relationship name assumptions)
                            Filters\TernaryFilter::make('active_3m')
                            ->label('Attended in last 3 months')
                            ->queries(
                                    true: fn(Builder $q) => self::applyActivityWindowExists($q, 90),
                                    false: fn(Builder $q) => self::applyInactivityWindowExists($q, 90),
                            ),
                            Filters\TernaryFilter::make('active_6m')
                            ->label('Attended in last 6 months')
                            ->queries(
                                    true: fn(Builder $q) => self::applyActivityWindowExists($q, 180),
                                    false: fn(Builder $q) => self::applyInactivityWindowExists($q, 180),
                            ),
                            Filters\TernaryFilter::make('active_12m')
                            ->label('Attended in last 12 months')
                            ->queries(
                                    true: fn(Builder $q) => self::applyActivityWindowExists($q, 365),
                                    false: fn(Builder $q) => self::applyInactivityWindowExists($q, 365),
                            ),
                        ])
                        ->actions([
                            Tables\Actions\ViewAction::make()->label('Open'),
                        ])
                        ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist {
        return $infolist->schema([
                    Info\Section::make('Mentee Summary')->columns(4)->schema([
                        Info\TextEntry::make('name')->label('Name')->weight('bold')->size('lg'),
                        Info\TextEntry::make('email'),
                        Info\TextEntry::make('phone'),
                        Info\TextEntry::make('created_at')->label('Onboarded')->since(),
                        Info\TextEntry::make('facility.name')->label('Facility')->icon('heroicon-o-building-office'),
                        Info\TextEntry::make('department.name')->label('Department')->icon('heroicon-o-squares-2x2'),
                        Info\TextEntry::make('cadre.name')->label('Cadre')->badge(),
                                Info\TextEntry::make('current_status')->label('Current Status')
                                ->state(fn(User $record) => static::currentStatus($record))
                                ->badge()
                                ->color(fn($state) => in_array($state, static::attritionStatuses(), true) ? 'danger' : ($state === 'study_leave' ? 'warning' : 'success')),
                    ]),
                    Info\Section::make('Training Activity')->columns(5)->schema([
                                Info\TextEntry::make('trainings_count')->label('Total Trainings')
                                ->state(fn(User $r) => $r->trainingParticipations()->count())
                                ->badge()->color('gray'),
                                Info\TextEntry::make('completed_count')->label('Completed')
                                ->state(fn(User $r) => (int) $r->trainingParticipations()->where('completion_status', 'completed')->count())
                                ->badge()->color('success'),
                                Info\TextEntry::make('overall_assessment')->label('Overall Assessment')
                                ->state(fn(User $r) => static::latestAssessmentResult($r))
                                ->formatStateUsing(fn($s) => $s ? strtoupper($s) : '—')
                                ->badge()
                                ->color(fn($s) => $s === 'pass' ? 'success' : ($s === 'fail' ? 'danger' : 'gray')),
                                Info\TextEntry::make('last_training_at')->label('Last Training')
                                ->state(fn(User $r) => static::computeLastTrainingDate($r))
                                ->date(),
                                Info\TextEntry::make('days_since')->label('Days Since Last')
                                ->state(fn(User $r) => static::computeDaysSinceLast($r))
                                ->suffix(' days'),
                    ]),
                    // Windowed summaries remain
                    Info\Tabs::make('windows')->tabs([
                        Info\Tabs\Tab::make('Past 3 months')->schema([static::windowSummaryEntry(90)]),
                        Info\Tabs\Tab::make('Past 6 months')->schema([static::windowSummaryEntry(180)]),
                        Info\Tabs\Tab::make('Past 12 months')->schema([static::windowSummaryEntry(365)]),
                    ]),
        ]);
    }

    public static function getPages(): array {

        return [
            'index' => Pages\ListMenteeProfiles::route('/'),
            'view' => Pages\ViewMenteeProfile::route('/{record}'),
        ];
    }

    public static function getRelations(): array {
        // Re-introduce the full trainings list on the profile
        return [
            TrainingParticipationsRelationManager::class,
        ];
    }

    /* ----------------------- Helpers ----------------------- */

    protected static function baseQuery(): Builder {
        return User::query()->with(['facility', 'department', 'cadre']);
    }

    public static function statusOptions(): array {
        $opts = MenteeStatus::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all();

        return !empty($opts) ? $opts : ['active' => 'Active', 'study_leave' => 'Study Leave'];
    }

    public static function attritionStatuses(): array {
        return ['resigned', 'transferred', 'retired', 'defected', 'deceased', 'suspended', 'dropped_out'];
    }

    public static function currentStatus(User $user): string {
        $latest = $user->statusLogs()->orderByDesc('effective_date')->orderByDesc('id')->first();
        return $latest->new_status ?? 'active';
    }

    /** Latest PASS/FAIL across all assessments for this user. */
    public static function latestAssessmentResult(User $user): ?string {
        $r = MenteeAssessmentResult::query()
                ->whereHas('participant', fn($q) => $q->where('user_id', $user->id))
                ->orderByDesc('assessment_date')
                ->orderByDesc('created_at')
                ->first();

        return $r?->result; // 'pass' | 'fail' | null
    }

    /** Latest PASS/FAIL for a specific participant (training) */
    public static function participantFinalResult(int $participantId): ?string {
        $r = MenteeAssessmentResult::query()
                ->where('participant_id', $participantId)
                ->orderByDesc('assessment_date')
                ->orderByDesc('created_at')
                ->first();

        return $r?->result;
    }

    public static function computeLastTrainingDate(User $user): ?string {
        $p = $user->trainingParticipations()->with('training:id,start_date,end_date')->get();
        $last = $p->map(function ($pi) {
                    return $pi->completion_date ?? optional($pi->training)->end_date ?? optional($pi->training)->start_date;
                })
                ->filter()
                ->max();

        return $last ? Carbon::parse($last)->toDateString() : null;
    }

    public static function computeDaysSinceLast(User $user): ?int {
        $date = self::computeLastTrainingDate($user);
        return $date ? Carbon::parse($date)->diffInDays(now()) : null;
    }

    /** Robust EXISTS-based window filter: attended something in last N days. */
    protected static function applyActivityWindowExists(Builder $q, int $days): Builder {
        $cut = now()->subDays($days)->toDateString();

        return $q->whereExists(function ($sub) use ($cut) {
                    $tp = DB::raw('training_participants as tp');
                    $tr = DB::raw('trainings as tr');
                    $sub->selectRaw('1')
                            ->from($tp)
                            ->leftJoin($tr, 'tr.id', '=', 'tp.training_id')
                            ->whereColumn('tp.user_id', 'users.id')
                            ->where(function ($w) use ($cut) {
                                $w->whereDate('tp.completion_date', '>=', $cut)
                                        ->orWhereDate('tr.start_date', '>=', $cut)
                                        ->orWhereDate('tr.end_date', '>=', $cut);
                            });
                });
    }

    /** Robust EXISTS-based window filter: did NOT attend anything in last N days. */
    protected static function applyInactivityWindowExists(Builder $q, int $days): Builder {
        $cut = now()->subDays($days)->toDateString();

        return $q->whereNotExists(function ($sub) use ($cut) {
                    $tp = DB::raw('training_participants as tp');
                    $tr = DB::raw('trainings as tr');
                    $sub->selectRaw('1')
                            ->from($tp)
                            ->leftJoin($tr, 'tr.id', '=', 'tp.training_id')
                            ->whereColumn('tp.user_id', 'users.id')
                            ->where(function ($w) use ($cut) {
                                $w->whereDate('tp.completion_date', '>=', $cut)
                                        ->orWhereDate('tr.start_date', '>=', $cut)
                                        ->orWhereDate('tr.end_date', '>=', $cut);
                            });
                });
    }

    /** Build a markdown summary for a given time window. */
    protected static function windowSummaryEntry(int $days): Info\TextEntry {
        return Info\TextEntry::make("win_{$days}_summary")
                        ->label('Activity & Changes')
                        ->markdown()
                        ->state(function (User $record) use ($days) {
                            $data = static::collectWindowData($record, $days);
                            $lines = [];

                            $lines[] = "**Window:** Last {$data['label']}";
                            $lines[] = '';
                            $lines[] = "**Training/Mentorship:**";
                            $lines[] = "- Sessions attended: **{$data['trainings']['count']}**";
                            if ($data['trainings']['count'] > 0) {
                                $lines[] = "- Recent:";
                                foreach ($data['trainings']['recent'] as $t)
                                    $lines[] = "  • {$t}";
                            } else {
                                $lines[] = "- None";
                            }

                            $lines[] = '';
                            $lines[] = "**Status changes:** " . ($data['status']['count'] ?: '0');
                            foreach ($data['status']['rows'] as $row)
                                $lines[] = "  • {$row}";

                            $lines[] = '';
                            $lines[] = "**Department changes:** " . ($data['department']['count'] ?: '0');
                            foreach ($data['department']['rows'] as $row)
                                $lines[] = "  • {$row}";

                            $lines[] = '';
                            $lines[] = "**Cadre changes:** " . ($data['cadre']['count'] ?: '0');
                            foreach ($data['cadre']['rows'] as $row)
                                $lines[] = "  • {$row}";

                            return implode("\n", $lines);
                        });
    }

    /** Gather windowed data for trainings + status + placement changes. */
    protected static function collectWindowData(User $user, int $days): array {
        $cut = now()->subDays($days)->startOfDay();
        $label = match ($days) { 90 => '3 months', 180 => '6 months', default => '12 months'
        };

        // Trainings attended in window
        $parts = $user->trainingParticipations()
                        ->with(['training:id,title,type,start_date,end_date'])
                        ->where(function ($q) use ($cut) {
                            $q->whereDate('completion_date', '>=', $cut)
                                    ->orWhereHas('training', function ($tq) use ($cut) {
                                        $tq->whereDate('start_date', '>=', $cut)
                                                ->orWhereDate('end_date', '>=', $cut);
                                    });
                        })->get();

        $trainRecent = $parts->sortByDesc(function ($p) {
                    return $p->completion_date ?? optional($p->training)->end_date ?? optional($p->training)->start_date;
                })->take(5)
                ->map(function ($p) {
                    $t = $p->training;
                    $date = ($p->completion_date ?? $t?->end_date ?? $t?->start_date)?->format('Y-m-d');
                    $scope = match ($t?->type) {
                        'facility_mentorship' => 'Mentorship',
                        'global_training' => 'MOH',
                        default => ucfirst(str_replace('_', ' ', $t?->type ?? '')),
                    };
                    $res = static::participantFinalResult($p->id);
                    $suffix = $res ? ' — Result: ' . strtoupper($res) : '';
                    return "{$date}: {$t?->title} ({$scope}){$suffix}";
                })
                ->values()
                ->all();

        // Status changes in window
        $statusLogs = $user->statusLogs()
                ->whereDate('effective_date', '>=', $cut)
                ->orderByDesc('effective_date')
                ->get();

        $statusRows = $statusLogs->map(function (MenteeStatusLog $log) {
                    $date = optional($log->effective_date)?->format('Y-m-d');
                    $from = $log->previous_status ? ucwords(str_replace('_', ' ', $log->previous_status)) : '—';
                    $to = $log->new_status ? ucwords(str_replace('_', ' ', $log->new_status)) : '—';
                    $why = $log->reason ? " — {$log->reason}" : '';
                    return "{$date}: {$from} → {$to}{$why}";
                })->values()->all();

        // Placement changes (Department/Cadre) in window
        $placeLogs = $user->placementLogs()
                ->whereDate('effective_date', '>=', $cut)
                ->latest('effective_date')
                ->get();

        $deptRows = $placeLogs->where('change_type', 'department')->map(function (MenteePlacementLog $pl) {
                    $date = optional($pl->effective_date)?->format('Y-m-d');
                    $from = $pl->oldDepartment?->name ?: '—';
                    $to = $pl->newDepartment?->name ?: '—';
                    $why = $pl->reason ? " — {$pl->reason}" : '';
                    return "{$date}: {$from} → {$to}{$why}";
                })->values()->all();

        $cadreRows = $placeLogs->where('change_type', 'cadre')->map(function (MenteePlacementLog $pl) {
                    $date = optional($pl->effective_date)?->format('Y-m-d');
                    $from = $pl->oldCadre?->name ?: '—';
                    $to = $pl->newCadre?->name ?: '—';
                    $why = $pl->reason ? " — {$pl->reason}" : '';
                    return "{$date}: {$from} → {$to}{$why}";
                })->values()->all();

        return [
            'label' => $label,
            'trainings' => ['count' => $parts->count(), 'recent' => $trainRecent],
            'status' => ['count' => $statusLogs->count(), 'rows' => $statusRows],
            'department' => ['count' => count($deptRows), 'rows' => $deptRows],
            'cadre' => ['count' => count($cadreRows), 'rows' => $cadreRows],
        ];
    }

    public static function getNavigationBadge(): ?string {
        $count = user::count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null {
        return 'warning';
    }
}
