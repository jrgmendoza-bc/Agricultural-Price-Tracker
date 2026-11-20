<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\PriceReport;
use Illuminate\View\View;

class MarketController extends Controller
{
    public function index(): View
    {
        $markets = Market::withCount('priceReports')->orderBy('market_name')->get();

        return view('markets.index', compact('markets'));
    }

    public function show(Market $market): View
    {
        // Latest report per product at this market.
        $latestPerProduct = PriceReport::with(['product.baseUnit', 'unit'])
            ->where('market_id', $market->id)
            ->whereIn('id', function ($query) use ($market) {
                $query->selectRaw('MAX(id)')
                    ->from('price_reports')
                    ->where('market_id', $market->id)
                    ->groupBy('product_id');
            })
            ->get()
            ->sortBy(fn ($report) => $report->product->product_name);

        return view('markets.show', compact('market', 'latestPerProduct'));
    }
}
