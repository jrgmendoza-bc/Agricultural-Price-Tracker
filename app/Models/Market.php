<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place where prices are observed — never an actor, never a seller.
 */
class Market extends Model
{
    protected $fillable = ['market_name', 'location', 'lat', 'lng'];

    public function priceReports(): HasMany
    {
        return $this->hasMany(PriceReport::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }
}
