<?php

namespace App\Models;

use App\Enums\PanelRole;
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
        'panel_activated_at' => 'datetime',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->canManagePanelContent();
    }

    public function canManagePanelContent(): bool
    {
        return $this->panel_activated_at !== null && $this->panelRole() !== null;
    }

    public function isPanelModerator(): bool
    {
        return $this->canManagePanelContent() && $this->panelRole() === PanelRole::SuperModerator;
    }

    public function panelRole(): ?PanelRole
    {
        return PanelRole::tryFrom($this->role ?? '');
    }
}
