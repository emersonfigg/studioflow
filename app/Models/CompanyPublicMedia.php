<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CompanyPublicMedia extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyPublicMediaFactory> */
    use HasFactory;

    protected $table = 'company_public_media';

    protected $fillable = [
        'company_id',
        'type',
        'path',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getUrlAttribute(): ?string
    {
        $url = MediaStorage::url($this->path);

        if ($url) {
            return $url;
        }

        $path = MediaStorage::normalizePath($this->path);

        return $path ? Storage::disk('public')->url($path) : null;
    }
}
