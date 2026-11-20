<?php

namespace App\Console\Commands;

use App\Models\PriceHistory;
use App\Models\PriceReport;
use Illuminate\Console\Command;

class SnapshotPrices extends Command
{
    protected $signature = 'prices:snapshot {date? : Day to aggregate (Y-m-d), defaults to today}';

    protected $description = 'Aggregate price reports into one price_history row per product/market/day';

    public function handle(): int
    {
        $date = $this->argument('date') ?? now()->toDateString();

        $aggregates = PriceReport::selectRaw(
            'product_id, market_id,
             AVG(normalized_price) as avg_price,
             MIN(normalized_price) as min_price,
             MAX(normalized_price) as max_price'
        )
            ->whereDate('date_reported', $date)
            ->groupBy('product_id', 'market_id')
            ->get();

        foreach ($aggregates as $row) {
            PriceHistory::updateOrCreate(
                [
                    'product_id' => $row->product_id,
                    'market_id' => $row->market_id,
                    'snapshot_date' => $date,
                ],
                [
                    'avg_normalized_price' => $row->avg_price,
                    'min_normalized_price' => $row->min_price,
                    'max_normalized_price' => $row->max_price,
                ],
            );
        }

        $this->info("Snapshotted {$aggregates->count()} product/market aggregates for {$date}.");

        return self::SUCCESS;
    }
}
