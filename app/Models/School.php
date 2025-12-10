<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class School extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',

        'kegiatan_rohani_utama' => 'array',
        'photos' => 'array',
        'quick_facts' => 'array',
        'tags' => 'array',
        'ai_flags' => 'array',

        'verified_at' => 'datetime',

        'is_published' => 'boolean',
        'tampil_di_peta' => 'boolean',
        'is_featured' => 'boolean',

        'menerima_siswa_berkebutuhan_khusus' => 'boolean',
        'fasilitas_abk_ringan' => 'boolean',
        'fasilitas_abk_sedang_berat' => 'boolean',
        'program_beasiswa_internal' => 'boolean',
        'program_beasiswa_eksternal' => 'boolean',

        'perpustakaan' => 'boolean',
        'lapangan_olahraga' => 'boolean',
        'kapel' => 'boolean',
        'aula' => 'boolean',
        'kantin_sehat' => 'boolean',

        // --- Harvy & DAPO snapshot ---
        'dapo_snapshot'      => 'array',
        'dapo_last_sync_at'  => 'immutable_datetime',
    ];

    public function foundation(): BelongsTo
    {
        return $this->belongsTo(Foundation::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
