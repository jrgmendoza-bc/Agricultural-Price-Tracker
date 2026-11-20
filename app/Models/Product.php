<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The TYPE hub. "Rice" exists once here; farmers' crops and market
 * price reports are instances/observations that FK into this table.
 */
class Product extends Model
{
    protected $fillable = ['product_name', 'category', 'base_unit_id'];

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function priceReports(): HasMany
    {
        return $this->hasMany(PriceReport::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function crops(): HasMany
    {
        return $this->hasMany(Crop::class);
    }

    /**
     * Convert a price expressed in ₱-per-$unitId into ₱-per-base-unit.
     * factor_to_base means "1 unit = X base units", so a ₱2400 sack
     * at 50 kg/sack normalizes to 2400 / 50 = ₱48/kg.
     *
     * Returns null when no conversion factor exists for this product+unit.
     */
    public function normalizePrice(float $price, int $unitId): ?float
    {
        if ($unitId === $this->base_unit_id) {
            return $price;
        }

        $factor = $this->productUnits()
            ->where('unit_id', $unitId)
            ->value('factor_to_base');

        if ($factor === null || (float) $factor == 0.0) {
            return null;
        }

        return $price / (float) $factor;
    }

    /**
     * Units this product may be priced/quantified in (base unit + conversions).
     */
    public function availableUnits()
    {
        return Unit::whereIn('id', array_merge(
            [$this->base_unit_id],
            $this->productUnits()->pluck('unit_id')->all(),
        ))->get();
    }

    /**
     * The current market rate: average normalized price across the
     * most recent reports (last 7 days), or null when no data.
     */
    public function currentMarketRate(): ?float
    {
        $avg = $this->priceReports()
            ->where('date_reported', '>=', now()->subDays(7))
            ->avg('normalized_price');

        return $avg !== null ? (float) $avg : null;
    }
}
