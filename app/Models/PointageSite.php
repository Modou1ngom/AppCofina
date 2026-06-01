<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointageSite extends Model
{
    protected $fillable = [
        'nom',
        'code_public',
        'secret_token',
        'description',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }
}
