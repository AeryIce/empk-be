<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Foundation extends Model
{
    use HasFactory;

    // Biar fleksibel, kita pakai guarded kosong
    protected $guarded = [];

    protected $casts = ['tags' => 'array'];

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }
}
