<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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
        'file_size' => 'integer',
    ];

    protected $with = ['resourceType', 'category', 'author'];

    // Add file-related attributes to appends
    protected $appends = [
        'file_url', 
        'thumbnail_url', 
        'formatted_file_size', 
        'read_time',
        'interaction_like_count',
        'interaction_dislike_count',
        'bookmark_count',
        'comment_count'
    ];

    // === EXISTING RELATIONSHIPS ===
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

    // === EXISTING QUERY SCOPES ===
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

    // === FILE-RELATED METHODS ===
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

    // === FILE-RELATED COMPUTED ATTRIBUTES ===
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }
        
        return route('resources.download', $this->slug);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->featured_image) {
            return null;
        }
        
        return Storage::disk('thumbnails')->url($this->featured_image);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    // === EXISTING COMPUTED ATTRIBUTES ===
    public function getReadTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return ceil($wordCount / 200); // Average reading speed
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

    // === FILE VALIDATION AND HELPERS ===
    public function hasFile(): bool
    {
        return !empty($this->file_path) && Storage::disk('resources')->exists($this->file_path);
    }

    public function hasValidFile(): bool
    {
        if (!$this->hasFile()) {
            return false;
        }

        // Check if file size matches stored size
        $actualSize = Storage::disk('resources')->size($this->file_path);
        return $actualSize === $this->file_size;
    }

    public function getFileExtension(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    public function isPreviewable(): bool
    {
        if (!$this->hasFile()) {
            return false;
        }

        $previewableTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain',
            'text/html',
            'video/mp4',
            'audio/mpeg',
        ];

        return in_array($this->file_type, $previewableTypes);
    }

    public function isImageFile(): bool
    {
        if (!$this->file_type) {
            return false;
        }

        return str_starts_with($this->file_type, 'image/');
    }

    public function isVideoFile(): bool
    {
        if (!$this->file_type) {
            return false;
        }

        return str_starts_with($this->file_type, 'video/');
    }

    public function isAudioFile(): bool
    {
        if (!$this->file_type) {
            return false;
        }

        return str_starts_with($this->file_type, 'audio/');
    }

    public function getFileTypeCategory(): string
    {
        if ($this->isImageFile()) {
            return 'image';
        }

        if ($this->isVideoFile()) {
            return 'video';
        }

        if ($this->isAudioFile()) {
            return 'audio';
        }

        return match($this->file_type) {
            'application/pdf' => 'pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'presentation',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'spreadsheet',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed' => 'archive',
            'text/plain',
            'text/csv' => 'text',
            default => 'document'
        };
    }

    // === FILE MANAGEMENT ===
    public function deleteFiles(): void
    {
        // Delete main file
        if ($this->file_path && Storage::disk('resources')->exists($this->file_path)) {
            Storage::disk('resources')->delete($this->file_path);
        }
        
        // Delete thumbnail
        if ($this->featured_image && Storage::disk('thumbnails')->exists($this->featured_image)) {
            Storage::disk('thumbnails')->delete($this->featured_image);
        }
    }

    public function copyFileTo(string $newPath): bool
    {
        if (!$this->hasFile()) {
            return false;
        }

        return Storage::disk('resources')->copy($this->file_path, $newPath);
    }

    // === MODEL EVENTS ===
    protected static function booted()
    {
        // Clean up files when resource is deleted
        static::deleting(function (Resource $resource) {
            $resource->deleteFiles();
        });

        // Generate slug automatically
        static::creating(function (Resource $resource) {
            if (empty($resource->slug)) {
                $resource->slug = \Str::slug($resource->title);
            }
        });

        // Handle tag synchronization when saving
        static::saved(function (Resource $resource) {
            // This will be handled in the Filament resource controller
            // or through a separate method for tag management
        });
    }

    // === ADDITIONAL HELPER METHODS ===
    public function getPreviewUrl(): ?string
    {
        if (!$this->isPreviewable()) {
            return null;
        }

        return route('resources.preview', $this->slug);
    }

    public function getDownloadFilename(): string
    {
        $extension = $this->getFileExtension();
        $filename = \Str::slug($this->title);
        
        return $extension ? "{$filename}.{$extension}" : $filename;
    }

    public function canBeDownloaded(): bool
    {
        return $this->is_downloadable && 
               $this->status === 'published' && 
               $this->hasFile();
    }

    public function getFileIcon(): string
    {
        return match($this->getFileTypeCategory()) {
            'pdf' => 'fas fa-file-pdf',
            'document' => 'fas fa-file-word',
            'presentation' => 'fas fa-file-powerpoint',
            'spreadsheet' => 'fas fa-file-excel',
            'image' => 'fas fa-file-image',
            'video' => 'fas fa-file-video',
            'audio' => 'fas fa-file-audio',
            'archive' => 'fas fa-file-archive',
            'text' => 'fas fa-file-alt',
            default => 'fas fa-file'
        };
    }

    public function getFileColor(): string
    {
        return match($this->getFileTypeCategory()) {
            'pdf' => 'text-red-600',
            'document' => 'text-blue-600',
            'presentation' => 'text-orange-600',
            'spreadsheet' => 'text-green-600',
            'image' => 'text-purple-600',
            'video' => 'text-pink-600',
            'audio' => 'text-indigo-600',
            'archive' => 'text-yellow-600',
            'text' => 'text-gray-600',
            default => 'text-gray-500'
        };
    }

    // === EXISTING ROUTE KEY ===
    public function getRouteKeyName()
    {
        return 'slug';
    }

    // === SEARCH ENHANCEMENT ===
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => strip_tags($this->content),
            'category_name' => $this->category?->name,
            'resource_type_name' => $this->resourceType?->name,
            'author_name' => $this->author?->full_name,
            'tags' => $this->tags->pluck('name')->toArray(),
            'difficulty_level' => $this->difficulty_level,
            'file_type' => $this->file_type,
            'is_downloadable' => $this->is_downloadable,
            'published_at' => $this->published_at?->timestamp,
            'view_count' => $this->view_count,
            'download_count' => $this->download_count,
        ];
    }
}