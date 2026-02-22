<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassModule extends Model {

    protected $fillable = [
        'mentorship_class_id',
        'program_module_id',
        'status',
        'started_at',
        'completed_at',
        'requires_assessment',
        'min_attendance_percentage',
        'order_sequence',
        'notes',
        'attendance_token',
        'attendance_link_active',
    ];
    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'requires_assessment' => 'boolean',
        'attendance_link_active' => 'boolean',
        'min_attendance_percentage' => 'decimal:2',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function mentorshipClass(): BelongsTo {
        return $this->belongsTo(MentorshipClass::class, 'mentorship_class_id');
    }

    public function programModule(): BelongsTo {
        return $this->belongsTo(ProgramModule::class, 'program_module_id');
    }

    public function sessions(): HasMany {
        return $this->hasMany(ClassSession::class, 'class_module_id')
                        ->orderBy('session_number');
    }

    public function assessments(): HasMany {
        return $this->hasMany(ModuleAssessment::class, 'class_module_id');
    }

    public function menteeProgress(): HasMany {
        return $this->hasMany(MenteeModuleProgress::class, 'class_module_id');
    }

    /**
     * Module-level attendance records (session_id = null).
     * These are written when a mentee self-confirms via dashboard.
     */
    public function attendanceRecords(): HasMany {
        return $this->hasMany(ClassAttendance::class, 'class_module_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lifecycle Guards
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Module can start if it is in 'not_started' state and the parent class is active.
     */
    public function canStart(): bool {
        if ($this->status !== 'not_started') {
            return false;
        }

        // Query the class status fresh from DB — never use the cached relationship here.
        // When called from MentorshipClass::start(), the class was just updated to 'active'
        // in the same transaction, but the in-memory relationship still holds the old status.
        $classStatus = \App\Models\MentorshipClass::where('id', $this->mentorship_class_id)
                ->value('status');

        return $classStatus === 'active';
    }

    /**
     * Module can be completed if it is in_progress.
     * Optionally enforce that all sessions are completed first (soft rule — warn only).
     */
    public function canComplete(): bool {
        return $this->status === 'in_progress';
    }

    /**
     * True if there are no session or progress records that would be orphaned.
     */
    public function canBeRemoved(): bool {
        return $this->status === 'not_started' && $this->sessions()->count() === 0 && $this->menteeProgress()->count() === 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lifecycle Transitions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Start the module: set status, generate attendance token, open attendance link.
     */
    public function start(): void {
        if (!$this->canStart()) {
            throw new \LogicException("Module [{$this->id}] cannot be started in its current state: {$this->status}");
        }

        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'attendance_token' => $this->attendance_token ?? Str::random(32),
            'attendance_link_active' => true,
        ]);

        // Initialise mentee_module_progress records for all enrolled mentees
        // who don't yet have a progress row for this module.
        $this->ensureMenteeProgressRecords();
    }

    /**
     * Complete the module: close attendance link, update progress for all mentees.
     */
    public function complete(): void {
        if (!$this->canComplete()) {
            throw new \LogicException("Module [{$this->id}] cannot be completed in its current state: {$this->status}");
        }

        DB::transaction(function () {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
                'attendance_link_active' => false,
            ]);

            // Calculate final attendance % and complete progress for each mentee
            $enrolledParticipants = ClassParticipant::where('mentorship_class_id', $this->mentorship_class_id)
                    ->whereIn('status', ['enrolled', 'active'])
                    ->get();

            $totalParticipants = $enrolledParticipants->count();
            if ($totalParticipants === 0) {
                return;
            }

            $confirmedUserIds = $this->attendanceRecords()->pluck('user_id')->toArray();

            foreach ($enrolledParticipants as $participant) {
                $attended = in_array($participant->user_id, $confirmedUserIds);
                $attendancePct = $attended ? 100.0 : 0.0;

                MenteeModuleProgress::updateOrCreate(
                        [
                            'class_participant_id' => $participant->id,
                            'class_module_id' => $this->id,
                        ],
                        [
                            'status' => $attended ? 'completed' : 'not_started',
                            'completed_at' => $attended ? now() : null,
                            'attendance_percentage' => $attendancePct,
                        ]
                );
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auto-Session Creation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Auto-populate class_sessions from the program module's template sessions.
     * Called immediately after a ClassModule is created.
     * Safe to call multiple times — skips if sessions already exist.
     *
     * @return int Number of sessions created
     */
    public function autoCreateSessions(): int {
        if ($this->sessions()->exists()) {
            return 0;
        }

        $templateSessions = ModuleSession::where('program_module_id', $this->program_module_id)
                ->where('is_active', true)
                ->orderBy('order_sequence')
                ->get();

        if ($templateSessions->isEmpty()) {
            return 0;
        }

        $created = 0;
        foreach ($templateSessions as $index => $template) {
            ClassSession::create([
                'class_module_id' => $this->id,
                'module_session_id' => $template->id,
                'session_number' => $template->order_sequence ?: ($index + 1),
                'title' => $template->name,
                'description' => $template->description,
                'duration_minutes' => $template->time_minutes,
                'status' => 'scheduled',
                'attendance_taken' => false,
            ]);
            $created++;
        }

        return $created;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attendance Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Count of mentees who have confirmed attendance for this module.
     */
    public function confirmedAttendanceCount(): int {
        return $this->attendanceRecords()->count();
    }

    /**
     * Total enrolled mentees in the parent class.
     */
    public function enrolledMenteeCount(): int {
        return ClassParticipant::where('mentorship_class_id', $this->mentorship_class_id)
                        ->whereIn('status', ['enrolled', 'active'])
                        ->count();
    }

    /**
     * Attendance rate as percentage (0-100).
     */
    public function attendanceRate(): float {
        $total = $this->enrolledMenteeCount();
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->confirmedAttendanceCount() / $total) * 100, 1);
    }

    /**
     * Whether a given user has already confirmed attendance for this module.
     */
    public function hasUserConfirmedAttendance(int $userId): bool {
        return $this->attendanceRecords()->where('user_id', $userId)->exists();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ensure every enrolled class participant has a progress row for this module.
     * Called on start() so the ManageModuleMentees page shows everyone immediately.
     */
    private function ensureMenteeProgressRecords(): void {
        $participants = ClassParticipant::where('mentorship_class_id', $this->mentorship_class_id)
                ->whereIn('status', ['enrolled', 'active'])
                ->get();

        foreach ($participants as $participant) {
            $progress = MenteeModuleProgress::firstOrCreate(
                    [
                        'class_participant_id' => $participant->id,
                        'class_module_id' => $this->id,
                    ],
                    [
                        'status' => 'in_progress',
                        'started_at' => now(),
                    ]
            );

            // firstOrCreate only sets status on NEW records.
            // If the row already existed as 'not_started' (enrolled before module started),
            // we must explicitly update it to 'in_progress'.
            if (!$progress->wasRecentlyCreated && $progress->status === 'not_started') {
                $progress->update([
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]);
            }
        }
    }

    /**
     * Formatted status label for display.
     */
    public function getStatusLabelAttribute(): string {
        return match ($this->status) {
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Filament badge color for status.
     */
    public function getStatusColorAttribute(): string {
        return match ($this->status) {
            'not_started' => 'gray',
            'in_progress' => 'warning',
            'completed' => 'success',
            default => 'gray',
        };
    }
}
