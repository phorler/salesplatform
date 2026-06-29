<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Global catalog entry for a book, keyed by ISBN-13. Not user-scoped.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'isbn13', 'isbn10', 'title', 'subtitle', 'authors', 'publisher',
        'published_year', 'page_count', 'cover_url', 'payload', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function priceObservations(): HasMany
    {
        return $this->hasMany(PriceObservation::class);
    }

    public function latestObservation(): HasOne
    {
        return $this->hasOne(PriceObservation::class)->latestOfMany('observed_at');
    }

    public function authorLine(): string
    {
        return implode(', ', $this->authors ?? []);
    }
}
