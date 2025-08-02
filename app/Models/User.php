<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'facility_id',
        'department_id',
        'cadre_id',
        'role',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'id_number',
        'phone',
        'status',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Computed Attributes
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getFullName1Attribute(): string
    {
        return $this->full_name;
    }

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function cadre(): BelongsTo
    {
        return $this->belongsTo(Cadre::class);
    }

    public function organizedTrainings(): HasMany
    {
        return $this->hasMany(Training::class, 'organizer_id');
    }

    public function trainingParticipations(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    // Many-to-many relationships for user access scoping
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'county_user');
    }

    public function subcounties(): BelongsToMany
    {
        return $this->belongsToMany(Subcounty::class, 'subcounty_user');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_user');
    }

    // Authorization Helper Methods
    public function isAboveSite(): bool
    {
        return $this->hasRole(['Super Admin', 'Division Lead', 'National Mentor Lead']);
    }

    public function scopedCountyIds()
    {
        return $this->isAboveSite()
            ? County::pluck('id')
            : $this->counties()->pluck('id');
    }

    public function scopedSubcountyIds()
    {
        return $this->isAboveSite()
            ? Subcounty::pluck('id')
            : $this->subcounties()->pluck('id');
    }

    public function scopedFacilityIds()
    {
        return $this->isAboveSite()
            ? Facility::pluck('facilities.id')
            : $this->facilities()->select('facilities.id')->pluck('facilities.id');
    }

    public function canAccessFacility(int $facilityId): bool
    {
        return true; //$this->isAboveSite() || $this->scopedFacilityIds()->contains($facilityId);
    }

    // Query Scopes
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }


    // Author relationship
    public function authoredResources(): HasMany
    {
        return $this->hasMany(Resource::class, 'author_id');
    }

    // Comments
    public function resourceComments(): HasMany
    {
        return $this->hasMany(ResourceComment::class);
    }

    // Interactions (likes, bookmarks, etc.)
    public function resourceInteractions(): HasMany
    {
        return $this->hasMany(ResourceInteraction::class);
    }

    // Access groups
    public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class, 'access_group_users');
    }

    // Resource views
    public function resourceViews(): HasMany
    {
        return $this->hasMany(ResourceView::class);
    }

    // Resource downloads
    public function resourceDownloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class);
    }

    // Helper methods for User model
    public function hasLikedResource(Resource $resource): bool
    {
        return $this->resourceInteractions()
            ->where('resource_id', $resource->id)
            ->where('type', 'like')
            ->exists();
    }

    public function hasDislikedResource(Resource $resource): bool
    {
        return $this->resourceInteractions()
            ->where('resource_id', $resource->id)
            ->where('type', 'dislike')
            ->exists();
    }

    public function hasBookmarkedResource(Resource $resource): bool
    {
        return $this->resourceInteractions()
            ->where('resource_id', $resource->id)
            ->where('type', 'bookmark')
            ->exists();
    }

    public function toggleResourceInteraction(Resource $resource, string $type): bool
    {
        $existing = $this->resourceInteractions()
            ->where('resource_id', $resource->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        $this->resourceInteractions()->create([
            'resource_id' => $resource->id,
            'type' => $type,
            'ip_address' => request()->ip(),
        ]);

        return true;
    }

    // Check if user can access a resource
    public function canAccessResource(Resource $resource): bool
    {
        return $resource->canUserAccess($this);
    }
}
