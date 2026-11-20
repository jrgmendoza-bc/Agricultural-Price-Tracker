<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\PriceReport;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceReportController extends Controller
{
    public function index(): View
    {
        $reports = PriceReport::with(['product.baseUnit', 'market', 'unit', 'reporter'])
            ->latest('date_reported')
            ->paginate(20);

        return view('reports.index', compact('reports'));
    }

    public function create(): View
    {
        $products = Product::with(['baseUnit', 'productUnits.unit'])->orderBy('product_name')->get();
        $markets = Market::orderBy('market_name')->get();

        return view('reports.create', compact('products', 'markets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'market_id' => ['required', 'exists:markets,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'date_reported' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $normalized = $product->normalizePrice((float) $data['price'], (int) $data['unit_id']);

        if ($normalized === null) {
            return back()->withInput()->withErrors([
                'unit_id' => "No conversion factor exists for {$product->product_name} in that unit. Pick one of its configured units.",
            ]);
        }

        PriceReport::create([
            ...$data,
            'normalized_price' => $normalized,
            'source_type' => 'manual',
            'reported_by' => $request->user()->id,
        ]);

        return redirect()->route('reports.index')->with('status', 'Price report recorded.');
    }
}
