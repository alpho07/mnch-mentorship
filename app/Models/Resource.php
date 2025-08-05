<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'meta_description',
        'featured_image',
        'resource_type_id',
        'category_id',
        'author_id',
        'status',
        'visibility',
        'is_featured',
        'is_downloadable',
        'download_count',
        'view_count',
        'like_count',
        'dislike_count',
        'published_at',
        'file_path',
        'file_size',
        'file_type',
        'external_url',
        'duration', // for videos/audio
        'difficulty_level',
        'prerequisites',
        'learning_outcomes',
        'sort_order',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_downloadable' => 'boolean',
        'prerequisites' => 'array',
        'learning_outcomes' => 'array',
    ];

    protected $with = ['resourceType', 'category', 'author'];

    // Relationships
    public function resourceType(): BelongsTo
    {
        return $this->belongsTo(ResourceType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'resource_tags');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ResourceComment::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ResourceInteraction::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(ResourceView::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class);
    }

    // Scope access control
    public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class, 'resource_access_groups');
    }

    public function scopedFacilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'resource_facilities');
    }

    public function scopedCounties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'resource_counties');
    }

    public function scopedDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'resource_departments');
    }

    // Query Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeAccessibleTo($query, ?User $user)
    {
        return $query->where(function ($q) use ($user) {
            // Always include public resources
            $q->where('visibility', 'public');
            
            // If user is authenticated, add additional access levels
            if ($user) {
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('visibility', 'authenticated')
                         ->orWhere('author_id', $user->id)
                         ->orWhereHas('accessGroups', fn($groupQ) =>
                             $groupQ->whereHas('users', fn($userQ) => $userQ->where('users.id', $user->id))
                         )
                         ->orWhereHas('scopedFacilities', fn($facQ) =>
                             $facQ->whereIn('facilities.id', $user->scopedFacilityIds())
                         );
                });
            }
        });
    }

    public function scopeByType($query, $type)
    {
        return $query->whereHas('resourceType', fn($q) => $q->where('slug', $type));
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopePopular($query, $days = 30)
    {
        return $query->withCount(['views' => fn($q) => $q->where('created_at', '>=', now()->subDays($days))])
                    ->orderByDesc('views_count');
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('excerpt', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%")
              ->orWhereHas('tags', fn($tagQ) => $tagQ->where('name', 'like', "%{$search}%"))
              ->orWhereHas('category', fn($catQ) => $catQ->where('name', 'like', "%{$search}%"));
        });
    }

    // Helper Methods
    public function canUserAccess(?User $user): bool
    {
        // Public resources are always accessible
        if ($this->visibility === 'public') {
            return true;
        }

        // If no user is provided (guest), only public resources are accessible
        if (!$user) {
            return false;
        }

        // Author can always access their own resources
        if ($this->author_id === $user->id) {
            return true;
        }

        // Authenticated users can access authenticated resources
        if ($this->visibility === 'authenticated') {
            return true;
        }

        // Check access groups
        if ($this->accessGroups()->whereHas('users', fn($q) => $q->where('users.id', $user->id))->exists()) {
            return true;
        }

        // Check scoped facilities
        if ($this->scopedFacilities()->whereIn('facilities.id', $user->scopedFacilityIds())->exists()) {
            return true;
        }

        return false;
    }

    public function incrementViews(?User $user = null, ?string $ipAddress = null): void
    {
        $this->increment('view_count');

        $this->views()->create([
            'user_id' => $user?->id,
            'ip_address' => $ipAddress ?: request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function incrementDownloads(?User $user = null): void
    {
        $this->increment('download_count');

        if ($user) {
            $this->downloads()->create([
                'user_id' => $user->id,
                'ip_address' => request()->ip(),
            ]);
        }
    }

    public function updateInteractionCounts(): void
    {
        $this->update([
            'like_count' => $this->interactions()->where('type', 'like')->count(),
            'dislike_count' => $this->interactions()->where('type', 'dislike')->count(),
        ]);
    }

    // Computed Attributes
    public function getReadTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return ceil($wordCount / 200); // Average reading speed
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) return '';

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function getInteractionLikeCountAttribute(): int
    {
        return $this->interactions()->where('type', 'like')->count();
    }

    public function getInteractionDislikeCountAttribute(): int
    {
        return $this->interactions()->where('type', 'dislike')->count();
    }

    public function getBookmarkCountAttribute(): int
    {
        return $this->interactions()->where('type', 'bookmark')->count();
    }

    public function getCommentCountAttribute(): int
    {
        return $this->comments()->approved()->count();
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }
}