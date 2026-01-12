<?php

namespace App\Models;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Database\Eloquent\Model;

class NewsPost extends Model
{
    protected $table = 'news_posts';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body_html',
        'youtube_url',
        'cover_path',
        'cover_alt',
        'labels',
        'categories',
        'tags',
        'facts',
        'event_start_at',
        'event_end_at',
        'location',
        'status',
        'published_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'labels' => 'array',
        'categories' => 'array',
        'tags' => 'array',
        'facts' => 'array',
        'event_start_at' => 'datetime',
        'event_end_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected $appends = [
        'cover_url',
    ];

    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->cover_path) {
            return null;
        }

        // Cloudinary public_id biasanya tanpa ekstensi
        $publicId = preg_replace('/\.(jpe?g|png|gif|webp|bmp|svg)$/i', '', (string) $this->cover_path) ?? (string) $this->cover_path;

        return (string) Cloudinary::image($publicId)->toUrl();
    }

    public function scopePublished($query)
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
