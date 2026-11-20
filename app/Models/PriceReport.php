<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OBSERVATION side: "rice costs ₱50/kg at Carmen market today."
 * A fact about a commodity at a place — nobody's personal price.
 */
class PriceReport extends Model
{
    protected $fillable = [
        'product_id',
        'market_id',
        'unit_id',
        'price',
        'normalized_price',
        'source_type',
        'reported_by',
        'date_reported',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'normalized_price' => 'decimal:4',
            'date_reported' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
