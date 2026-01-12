<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\Request;

class NewsPostController extends Controller
{
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 200));

        $items = NewsPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $items->map(fn (NewsPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'youtube_url' => $p->youtube_url,
                'cover_url' => $p->cover_url,
                'cover_alt' => $p->cover_alt,
                'labels' => $p->labels ?? [],
                'categories' => $p->categories ?? [],
                'tags' => $p->tags ?? [],
                'facts' => $p->facts ?? [],
                'published_at' => optional($p->published_at)->toIso8601String(),
            ]),
        ]);
    }

    public function show(string $slug)
    {
        $post = NewsPost::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if (!$post) {
            return response()->json([
                'ok' => false,
                'message' => 'Not found',
            ], 404);
        }

        // NOTE: Konten berita dibuat oleh admin via Filament.
        // Untuk MVP, kita kirim HTML apa adanya.
        // Kalau nanti butuh sanitasi ketat (anti-XSS), kita pasang sanitizer (Symfony HTML Sanitizer / HTML Purifier) di step berikutnya.
        $safeHtml = $post->body_html;

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'body_html' => $safeHtml,
                'youtube_url' => $post->youtube_url,
                'cover_url' => $post->cover_url,
                'cover_alt' => $post->cover_alt,
                'labels' => $post->labels ?? [],
                'categories' => $post->categories ?? [],
                'tags' => $post->tags ?? [],
                'facts' => $post->facts ?? [],
                'published_at' => optional($post->published_at)->toIso8601String(),
            ],
        ]);
    }
}
