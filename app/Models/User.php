<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * Atur kolom yang boleh mass-assign (sesuaikan kalau kamu sudah punya).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Hidden & casts standar.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        // Kalau project-mu sudah pakai cast hashed di Laravel 10+, boleh aktifkan:
        // 'password' => 'hashed',
    ];

    /**
     * Izinkan user mengakses panel Filament.
     * Untuk demo, kita allow semua user yang berhasil login.
     * Nanti bisa kamu batasi per-role/per-email.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;

        // Contoh pembatasan:
        // return str_ends_with($this->email, '@mpk.local');
        // atau cek role:
        // return in_array($this->role, ['superadmin','admin']);
    }
}
