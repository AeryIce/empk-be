<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('auth.passwords.users.table'), function (Blueprint $table) {
            $table->string('panel_activation_delivery', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('auth.passwords.users.table'), function (Blueprint $table) {
            $table->dropColumn('panel_activation_delivery');
        });
    }
};
