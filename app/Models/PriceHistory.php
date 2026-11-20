<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $table = 'price_history';

    protected $fillable = [
        'product_id',
        'market_id',
        'snapshot_date',
        'avg_normalized_price',
        'min_normalized_price',
        'max_normalized_price',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'avg_normalized_price' => 'decimal:4',
            'min_normalized_price' => 'decimal:4',
            'max_normalized_price' => 'decimal:4',
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
}
