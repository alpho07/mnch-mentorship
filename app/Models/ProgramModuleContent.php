<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramModuleContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_module_id',
        'type',
        'title',
        'content',
        'video_url',
        'video_path',
        'order_sequence',
        'is_active',
    ];

    protected $casts = [
        'order_sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    public function programModule(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class);
    }

    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    public function isIntroduction(): bool
    {
        return $this->type === 'introduction';
    }

    public function isCaseScenario(): bool
    {
        return $this->type === 'case_scenario';
    }

    public function youtubeEmbedUrl(): ?string
    {
        $url = $this->video_url;

        if (empty($url)) {
            return null;
        }

        $videoId = $this->extractYoutubeVideoId($url);

        return $videoId ? "https://www.youtube.com/embed/{$videoId}" : null;
    }

    public function isYoutubeVideo(): bool
    {
        return $this->youtubeEmbedUrl() !== null;
    }

    private function extractYoutubeVideoId(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
