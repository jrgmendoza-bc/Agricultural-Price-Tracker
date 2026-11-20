<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::with('baseUnit')
            ->withCount('priceReports')
            ->orderBy('category')
            ->orderBy('product_name')
            ->get();

        return view('products.index', compact('products'));
    }

    public function show(Product $product): View
    {
        $product->load('baseUnit', 'productUnits.unit');

        // 30-day trend per market for the chart.
        $history = PriceHistory::with('market')
            ->where('product_id', $product->id)
            ->where('snapshot_date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('snapshot_date')
            ->get();

        $chartSeries = $history->groupBy('market.market_name')->map(
            fn ($rows) => $rows->map(fn ($row) => [
                'date' => $row->snapshot_date->toDateString(),
                'avg' => (float) $row->avg_normalized_price,
            ])->values()
        );

        $recentReports = $product->priceReports()
            ->with(['market', 'unit', 'reporter'])
            ->latest('date_reported')
            ->limit(15)
            ->get();

        $availableCrops = $product->crops()
            ->with(['farmer', 'unit'])
            ->where('status', 'available')
            ->orderBy('normalized_asking_price')
            ->get();

        $marketRate = $product->currentMarketRate();

        return view('products.show', compact('product', 'chartSeries', 'recentReports', 'availableCrops', 'marketRate'));
    }
}
