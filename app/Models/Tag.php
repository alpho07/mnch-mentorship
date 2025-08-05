<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'color'];

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_tags');
    }

    // Query Scopes
    public function scopeByName($query, string $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->withCount('resources')
                    ->orderByDesc('resources_count')
                    ->limit($limit);
    }

    // Computed Attributes
    public function getResourceCountAttribute(): int
    {
        return $this->resources()->count();
    }
}
