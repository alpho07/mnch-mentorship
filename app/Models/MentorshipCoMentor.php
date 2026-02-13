<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipCoMentor extends Model {

    use HasFactory;

    protected $fillable = [
        'training_id',
        'user_id',
        'invited_by',
        'invited_at',
        'accepted_at',
        'status', // pending | accepted | declined | revoked | removed
        'invitation_token', // unique token for invitation link
        'permissions',
    ];
    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'permissions' => 'array',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function training(): BelongsTo {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopePending($query) {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query) {
        return $query->where('status', 'accepted');
    }

    public function scopeActive($query) {
        return $query->where('status', 'accepted');
    }

    public function scopeRevoked($query) {
        return $query->where('status', 'revoked');
    }

    // ==========================================
    // COMPUTED ATTRIBUTES
    // ==========================================

    public function getIsAcceptedAttribute(): bool {
        return $this->status === 'accepted';
    }

    public function getIsPendingAttribute(): bool {
        return $this->status === 'pending';
    }

    public function getIsRevokedAttribute(): bool {
        return $this->status === 'revoked';
    }

    /**
     * Check if this co-mentor has active access.
     * Only accepted status grants access. Revoked immediately loses it.
     */
    public function hasAccess(): bool {
        return $this->status === 'accepted';
    }

    // ==========================================
    // STATUS TRANSITION METHODS
    // ==========================================

    public function accept(): void {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function decline(): void {
        $this->update(['status' => 'declined']);
    }

    /**
     * Revoke a pending invitation. Co-mentor cannot accept after revocation.
     */
    public function revoke(): void {
        $this->update(['status' => 'revoked']);
    }

    /**
     * Remove an accepted co-mentor. Immediately loses all access.
     */
    public function remove(): void {
        $this->update(['status' => 'removed']);
    }

    // ==========================================
    // PERMISSION CHECKS
    // ==========================================

    public function canFacilitate(): bool {
        return $this->hasAccess() &&
                ($this->permissions['can_facilitate'] ?? true);
    }

    public function canCreateClasses(): bool {
        return $this->hasAccess() &&
                ($this->permissions['can_create_classes'] ?? false);
    }

    public function canInviteMentors(): bool {
        return $this->hasAccess() &&
                ($this->permissions['can_invite_mentors'] ?? false);
    }
}
