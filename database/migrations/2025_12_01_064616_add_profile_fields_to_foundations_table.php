<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foundations', function (Blueprint $table): void {
            // Narasi profil
            $table->text('about_text')->nullable();
            $table->text('vision_text')->nullable();
            $table->text('mission_text')->nullable();
            $table->string('motto', 255)->nullable();
            $table->text('core_values')->nullable();
            $table->text('program_unggulan')->nullable();
            $table->text('services_text')->nullable();

            // Pengurus inti
            $table->string('chair_name', 150)->nullable();
            $table->string('secretary_name', 150)->nullable();
            $table->string('treasurer_name', 150)->nullable();
            $table->text('board_text')->nullable();

            // Sosmed & kontak tambahan
            $table->string('instagram', 100)->nullable();
            $table->string('facebook', 100)->nullable();
            $table->string('youtube', 100)->nullable();
            $table->string('whatsapp', 30)->nullable();

            // Media tambahan
            $table->string('qr_url', 255)->nullable();
            $table->string('profile_image_url', 255)->nullable();
            $table->string('brochure_pdf_url', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('foundations', function (Blueprint $table): void {
            $table->dropColumn([
                'about_text',
                'vision_text',
                'mission_text',
                'motto',
                'core_values',
                'program_unggulan',
                'services_text',
                'chair_name',
                'secretary_name',
                'treasurer_name',
                'board_text',
                'instagram',
                'facebook',
                'youtube',
                'whatsapp',
                'qr_url',
                'profile_image_url',
                'brochure_pdf_url',
            ]);
        });
    }
};
