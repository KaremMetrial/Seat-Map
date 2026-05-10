<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Venue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'venue_type',
        'default_width',
        'default_height',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $venue): void {
            if (empty($venue->slug)) {
                $venue->slug = Str::slug($venue->name);
            }
        });
    }

    public function templates(): HasMany
    {
        return $this->hasMany(VenueTemplate::class);
    }

    public function defaultTemplate(): ?VenueTemplate
    {
        return $this->templates()->where('is_default', true)->first();
    }
}
