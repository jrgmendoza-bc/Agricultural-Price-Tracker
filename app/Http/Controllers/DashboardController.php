<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\Market;
use App\Models\PriceReport;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The core screen: current market rates per product, latest reports,
     * and (for farmers) how their asking prices sit against the market.
     */
    public function index(Request $request): View
    {
        $products = Product::with('baseUnit')->orderBy('product_name')->get();

        // Current rate per product = avg normalized price over the last 7 days.
        $rates = PriceReport::selectRaw('product_id, AVG(normalized_price) as avg_price, MIN(normalized_price) as min_price, MAX(normalized_price) as max_price, COUNT(*) as report_count')
            ->where('date_reported', '>=', now()->subDays(7))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $latestReports = PriceReport::with(['product.baseUnit', 'market', 'unit'])
            ->latest('date_reported')
            ->limit(10)
            ->get();

        $marketCount = Market::count();

        $myCrops = null;
        if ($request->user()->isFarmer()) {
            $myCrops = $request->user()->crops()
                ->with(['product.baseUnit', 'unit'])
                ->where('status', '!=', 'sold')
                ->get();
        }

        return view('dashboard.index', compact('products', 'rates', 'latestReports', 'marketCount', 'myCrops'));
    }
}
