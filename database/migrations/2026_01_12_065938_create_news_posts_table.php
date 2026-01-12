<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_posts', function (Blueprint $table): void {
            $table->id();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();

            $table->text('excerpt')->nullable();
            $table->longText('body_html')->nullable();

            $table->string('youtube_url')->nullable();

            // Cloudinary file path (public_id / path on disk)
            $table->string('cover_path')->nullable();
            $table->string('cover_alt')->nullable();

            // Simple SEO / grouping
            $table->json('labels')->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->json('facts')->nullable();

            // Event metadata (optional)
            $table->timestampTz('event_start_at')->nullable();
            $table->timestampTz('event_end_at')->nullable();
            $table->string('location')->nullable();

            // Publishing
            $table->string('status', 20)->default('draft'); // draft | published
            $table->timestampTz('published_at')->nullable();

            // Optional: who created it
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
