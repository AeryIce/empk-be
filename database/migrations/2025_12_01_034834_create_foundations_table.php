<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foundations', function (Blueprint $table): void {
            $table->id();

            $table->string('name', 150);
            $table->string('slug', 160)->unique();

            $table->string('kode', 50)->nullable();
            $table->string('alias', 150)->nullable();
            $table->string('jenis', 100)->nullable();

            $table->string('alamat', 255)->nullable();
            $table->string('kabkota', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->string('kode_pos', 10)->nullable();

            $table->string('telepon', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 255)->nullable();

            $table->string('logo_url', 255)->nullable();
            $table->string('cover_url', 255)->nullable();

            $table->boolean('is_active')->default(true);

            $table->json('tags')->nullable();
            $table->text('notes_internal')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foundations');
    }
};
