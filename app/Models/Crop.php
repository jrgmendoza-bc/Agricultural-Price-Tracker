<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * INSTANCE side: a farmer's physical batch of a product.
 * Master = the farmer (owns and edits the row); audience = buyers.
 * Carries the farmer's asking price — distinct from the market price.
 */
class Crop extends Model
{
    protected $fillable = [
        'farmer_id',
        'product_id',
        'unit_id',
        'quantity',
        'asking_price',
        'normalized_asking_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'asking_price' => 'decimal:2',
            'normalized_asking_price' => 'decimal:4',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * How this crop's ask compares to the going market rate, as a
     * percentage difference (+ above market, - below). Null when the
     * market has no recent data to compare against.
     */
    public function vsMarketRate(): ?float
    {
        $rate = $this->product->currentMarketRate();

        if ($rate === null || $rate == 0.0) {
            return null;
        }

        return (((float) $this->normalized_asking_price - $rate) / $rate) * 100;
    }
}
