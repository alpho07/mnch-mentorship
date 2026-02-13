<?php

namespace App\Services;

use App\Models\ClassAttendance;
use Illuminate\Support\Facades\DB;

/**
 * Sole authority for creating attendance records.
 * 
 * DOMAIN RULES:
 * - Attendance records are IMMUTABLE once created (enforced at model level).
 * - All attendance MUST be created through this service.
 * - Controllers and pages MUST NOT insert into class_attendances directly.
 * - Auto-marked attendance occurs via EnrollmentService (invite link enrollment).
 * - Manual attendance is marked by mentors/co-mentors via Filament pages.
 */
class AttendanceService
{
    /**
     * Mark attendance for a user in a class/session.
     * 
     * Idempotent: If attendance already exists, returns the existing record.
     * Immutable: Once created, the record cannot be updated or deleted.
     * 
     * @param int      $classId   The mentorship class ID
     * @param int|null $sessionId The session ID (null for enrollment-level attendance)
     * @param int      $userId    The user being marked
     * @param int      $markedBy  The user performing the marking
     * @param string   $source    'auto' (via invite link) or 'manual' (by mentor)
     * 
     * @return ClassAttendance The created or existing attendance record
     */
    public function markAttendance(
        int $classId,
        ?int $sessionId,
        int $userId,
        int $markedBy,
        string $source = 'manual'
    ): ClassAttendance {
        // Idempotent: check if already exists
        $existing = $this->findAttendance($classId, $sessionId, $userId);

        if ($existing) {
            return $existing;
        }

        // Create within transaction for safety
        return DB::transaction(function () use ($classId, $sessionId, $userId, $markedBy, $source) {
            // Double-check within transaction (race condition guard)
            $existing = $this->findAttendance($classId, $sessionId, $userId);
            if ($existing) {
                return $existing;
            }

            return ClassAttendance::create([
                'class_id'   => $classId,
                'session_id' => $sessionId,
                'user_id'    => $userId,
                'marked_by'  => $markedBy,
                'marked_at'  => now(),
                'source'     => $source,
            ]);
        });
    }

    /**
     * Check if attendance exists for a user in a class/session.
     */
    public function hasAttendance(int $classId, ?int $sessionId, int $userId): bool
    {
        return $this->findAttendance($classId, $sessionId, $userId) !== null;
    }

    /**
     * Find an existing attendance record.
     */
    public function findAttendance(int $classId, ?int $sessionId, int $userId): ?ClassAttendance
    {
        return ClassAttendance::where('class_id', $classId)
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get all attendance records for a class.
     */
    public function getClassAttendance(int $classId): \Illuminate\Database\Eloquent\Collection
    {
        return ClassAttendance::where('class_id', $classId)
            ->with(['user', 'marker'])
            ->orderBy('marked_at')
            ->get();
    }

    /**
     * Get attendance for a specific session.
     */
    public function getSessionAttendance(int $classId, int $sessionId): \Illuminate\Database\Eloquent\Collection
    {
        return ClassAttendance::where('class_id', $classId)
            ->where('session_id', $sessionId)
            ->with(['user', 'marker'])
            ->orderBy('marked_at')
            ->get();
    }

    /**
     * Get attendance count for a user across a class.
     */
    public function getUserAttendanceCount(int $classId, int $userId): int
    {
        return ClassAttendance::where('class_id', $classId)
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Get attendance rate for a class (percentage of expected records that exist).
     * 
     * @param int $classId
     * @param int $totalExpected Total expected attendance records (sessions × participants)
     */
    public function getClassAttendanceRate(int $classId, int $totalExpected): float
    {
        if ($totalExpected === 0) {
            return 0.0;
        }

        $actual = ClassAttendance::where('class_id', $classId)->count();

        return round(($actual / $totalExpected) * 100, 1);
    }

    /**
     * Bulk mark attendance for multiple users in a session.
     * Used by mentor when marking attendance from Filament page.
     * 
     * @return int Number of new records created
     */
    public function bulkMarkAttendance(
        int $classId,
        int $sessionId,
        array $userIds,
        int $markedBy,
        string $source = 'manual'
    ): int {
        $created = 0;

        DB::transaction(function () use ($classId, $sessionId, $userIds, $markedBy, $source, &$created) {
            foreach ($userIds as $userId) {
                $existing = $this->findAttendance($classId, $sessionId, $userId);
                if (! $existing) {
                    ClassAttendance::create([
                        'class_id'   => $classId,
                        'session_id' => $sessionId,
                        'user_id'    => $userId,
                        'marked_by'  => $markedBy,
                        'marked_at'  => now(),
                        'source'     => $source,
                    ]);
                    $created++;
                }
            }
        });

        return $created;
    }
}
