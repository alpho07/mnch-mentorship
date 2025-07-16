<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [


        'password',
        'facility_id',
        'cadre_id',
        'role',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'id_number',
        'phone',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
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

    public function homeFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function cadre(): BelongsTo
    {
        return $this->belongsTo(Cadre::class, 'cadre_id');
    }

    // Check if user is above-site (Super Admin/Division/National)
    public function isAboveSite()
    {
        return $this->hasRole(['Super Admin', 'Division Lead', 'National Mentor Lead']);
    }

    // Returns all allowed county IDs for this user
    public function scopedCountyIds()
    {
        return $this->isAboveSite()
            ? \App\Models\County::pluck('id')
            : $this->counties()->pluck('id');
    }

    // Returns all allowed subcounty IDs
    public function scopedSubcountyIds()
    {
        return $this->isAboveSite()
            ? \App\Models\Subcounty::pluck('id')
            : $this->subcounties()->pluck('id');
    }

    // Returns all allowed facility IDs
    public function scopedFacilityIds()
    {
        return $this->isAboveSite()
            ? \App\Models\Facility::pluck('id')
            : $this->facilities()->pluck('id');
    }

    // Returns true if user can access a specific facility
    public function canAccessFacility($facilityId)
    {
        return $this->isAboveSite() || $this->scopedFacilityIds()->contains($facilityId);
    }

      public function organizedTrainings()
    {
        return $this->hasMany(Training::class, 'organizer_id');
    }
}
