<?php

// app/Filament/Resources/MenteeProfileResource/Pages/ViewMenteeProfile.php

namespace App\Filament\Resources\MenteeProfileResource\Pages;

use App\Filament\Resources\MenteeProfileResource;
use App\Models\Cadre;
use App\Models\Department;
use App\Models\MenteePlacementLog;
use App\Models\MenteeStatus;
use App\Models\MenteeStatusLog;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Schema;

class ViewMenteeProfile extends ViewRecord {

    protected static string $resource = MenteeProfileResource::class;

    protected function getHeaderActions(): array {
        return [
            // -------------------- STATUS (single) --------------------
                    Actions\Action::make('update_status')
                    ->label('Update Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('status_id')
                        ->label('New Status')
                        ->options(fn() => MenteeStatus::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                        ->required()
                        ->native(false)
                        ->searchable(),
                        Forms\Components\Radio::make('review_window')
                        ->label('Review Window')
                        ->options(['3m' => '3 months', '6m' => '6 months', '12m' => '12 months'])
                        ->inline()
                        ->required(),
                        Forms\Components\DatePicker::make('effective_date')
                        ->label('Effective Date')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                        Forms\Components\TextInput::make('reason')
                        ->required()
                        ->maxLength(255),
                        Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->maxLength(2000),
                    ])
                    ->action(function (array $data, User $record) {
                        $table = (new MenteeStatusLog)->getTable();
                        $has = fn(string $col) => Schema::hasColumn($table, $col);

                        $status = MenteeStatus::find($data['status_id']);

                        $payload = [
                            'user_id' => $record->id,
                            'effective_date' => $data['effective_date'],
                            'reason' => $data['reason'],
                            'notes' => $data['notes'] ?? null,
                        ];

                        if ($has('new_status'))
                            $payload['new_status'] = $status?->name;
                        if ($has('mentee_status_id') && $status)
                            $payload['mentee_status_id'] = $status->id;
                        if ($has('previous_status'))
                            $payload['previous_status'] = MenteeProfileResource::currentStatus($record);

                        // One-of 3m/6m/12m flags
                        foreach (['review_3m', 'review_6m', 'review_12m'] as $col) {
                            if ($has($col))
                                $payload[$col] = false;
                        }
                        $choice = $data['review_window'] ?? null;
                        if ($choice === '3m' && $has('review_3m'))
                            $payload['review_3m'] = true;
                        if ($choice === '6m' && $has('review_6m'))
                            $payload['review_6m'] = true;
                        if ($choice === '12m' && $has('review_12m'))
                            $payload['review_12m'] = true;

                        MenteeStatusLog::create($payload);

                        $record->refresh();
                        Notification::make()->title('Status updated')->success()->send();
                    }),
            // -------------------- DEPARTMENT (single) --------------------
            Actions\Action::make('update_department')
                    ->label('Update Department')
                    ->icon('heroicon-o-building-office')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('department_id')
                        ->label('Department')
                        ->options(fn() => Department::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->native(false)
                        ->searchable(),
                        Forms\Components\DatePicker::make('effective_date')
                        ->label('Effective Date')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                        Forms\Components\TextInput::make('reason')->maxLength(255),
                        Forms\Components\Textarea::make('notes')->rows(2)->maxLength(2000),
                    ])
                    ->action(function (array $data, User $record) {
                        $old = $record->department_id;
                        $new = $data['department_id'];

                        MenteePlacementLog::create([
                            'user_id' => $record->id,
                            'change_type' => 'department',
                            'old_department_id' => $old,
                            'new_department_id' => $new,
                            'effective_date' => $data['effective_date'],
                            'reason' => $data['reason'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $record->update(['department_id' => $new]);
                        $record->refresh();
                        Notification::make()->title('Department updated')->success()->send();
                    }),
            // -------------------- CADRE (single) --------------------
            Actions\Action::make('update_cadre')
                    ->label('Update Cadre')
                    ->icon('heroicon-o-user-group')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('cadre_id')
                        ->label('Cadre')
                        ->options(fn() => Cadre::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->native(false)
                        ->searchable(),
                        Forms\Components\DatePicker::make('effective_date')
                        ->label('Effective Date')
                        ->default(now())
                        ->maxDate(now())
                        ->required(),
                        Forms\Components\TextInput::make('reason')->maxLength(255),
                        Forms\Components\Textarea::make('notes')->rows(2)->maxLength(2000),
                    ])
                    ->action(function (array $data, User $record) {
                        $old = $record->cadre_id;
                        $new = $data['cadre_id'];

                        MenteePlacementLog::create([
                            'user_id' => $record->id,
                            'change_type' => 'cadre',
                            'old_cadre_id' => $old,
                            'new_cadre_id' => $new,
                            'effective_date' => $data['effective_date'],
                            'reason' => $data['reason'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $record->update(['cadre_id' => $new]);
                        $record->refresh();
                        Notification::make()->title('Cadre updated')->success()->send();
                    }),
        ];
    }

    /**
     * (Optional) CSV export. If you use it, adjust your route/action.
     * This version outputs per-training final Result (PASS/FAIL).
     */
    protected function exportMenteeProfile() {
        /** @var User $u */
        $u = $this->record->load(['facility', 'department', 'cadre', 'trainingParticipations.training']);

        $rows = [];
        $rows[] = ['Field', 'Value'];
        $rows[] = ['Name', $u->name];
        $rows[] = ['Email', $u->email];
        $rows[] = ['Phone', $u->phone];
        $rows[] = ['Facility', optional($u->facility)->name];
        $rows[] = ['Department', optional($u->department)->name];
        $rows[] = ['Cadre', optional($u->cadre)->name];
        $rows[] = ['Current Status', \App\Filament\Resources\MenteeProfileResource::currentStatus($u)];
        $rows[] = ['Overall Assessment', strtoupper(\App\Filament\Resources\MenteeProfileResource::latestAssessmentResult($u) ?? '')];
        $rows[] = ['Last Training', \App\Filament\Resources\MenteeProfileResource::computeLastTrainingDate($u)];
        $rows[] = ['Days Since Last', \App\Filament\Resources\MenteeProfileResource::computeDaysSinceLast($u)];
        $rows[] = [];
        $rows[] = ['Training History'];
        $rows[] = ['Title', 'Scope', 'Led By', 'Start', 'End', 'Attendance', 'Completion', 'Completed On', 'Result'];

        foreach ($u->trainingParticipations as $p) {
            $t = $p->training;
            $res = \App\Filament\Resources\MenteeProfileResource::participantFinalResult($p->id);

            $rows[] = [
                $t->title ?? '',
                match ($t->type ?? null) {
                    'facility_mentorship' => 'Mentorship',
                    'global_training' => 'MOH/Global',
                    default => ucfirst(str_replace('_', ' ', $t->type ?? '')),
                },
                $t->lead_type ?? '',
                optional($t->start_date)->format('Y-m-d') ?? '',
                optional($t->end_date)->format('Y-m-d') ?? '',
                $p->attendance_status ?? '',
                $p->completion_status ?? '',
                optional($p->completion_date)->format('Y-m-d') ?? '',
                $res ? strtoupper($res) : '',
            ];
        }

        $filename = 'mentee_profile_' . $u->id . '.csv';

        return response()->streamDownload(function () use ($rows) {
                    $out = fopen('php://output', 'w');
                    foreach ($rows as $r)
                        fputcsv($out, $r);
                    fclose($out);
                }, $filename, ['Content-Type' => 'text/csv']);
    }
}
