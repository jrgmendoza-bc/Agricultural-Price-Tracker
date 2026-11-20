<?php

namespace App\Http\Controllers;

use App\Models\Crop;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CropController extends Controller
{
    /**
     * Buyer-facing browse: every available crop, cheapest normalized
     * ask first, with the market-rate comparison that is the point
     * of having both price concepts in one system.
     */
    public function browse(Request $request): View
    {
        $query = Crop::with(['farmer', 'product.baseUnit', 'unit'])
            ->where('status', 'available')
            ->orderBy('normalized_asking_price');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        $crops = $query->get();
        $products = Product::orderBy('product_name')->get();

        return view('crops.browse', compact('crops', 'products'));
    }

    /** Farmer's own listings. */
    public function index(Request $request): View
    {
        $crops = $request->user()->crops()
            ->with(['product.baseUnit', 'unit'])
            ->latest()
            ->get();

        return view('crops.index', compact('crops'));
    }

    public function create(): View
    {
        $products = Product::with(['baseUnit', 'productUnits.unit'])->orderBy('product_name')->get();

        return view('crops.create', compact('products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $product = Product::findOrFail($data['product_id']);
        $normalized = $product->normalizePrice((float) $data['asking_price'], (int) $data['unit_id']);

        if ($normalized === null) {
            return back()->withInput()->withErrors([
                'unit_id' => "No conversion factor exists for {$product->product_name} in that unit.",
            ]);
        }

        $request->user()->crops()->create([
            ...$data,
            'normalized_asking_price' => $normalized,
        ]);

        return redirect()->route('crops.index')->with('status', 'Crop listed.');
    }

    public function edit(Request $request, Crop $crop): View
    {
        $this->authorizeOwner($request, $crop);

        $products = Product::with(['baseUnit', 'productUnits.unit'])->orderBy('product_name')->get();

        return view('crops.edit', compact('crop', 'products'));
    }

    public function update(Request $request, Crop $crop): RedirectResponse
    {
        $this->authorizeOwner($request, $crop);

        $data = $this->validated($request);

        $product = Product::findOrFail($data['product_id']);
        $normalized = $product->normalizePrice((float) $data['asking_price'], (int) $data['unit_id']);

        if ($normalized === null) {
            return back()->withInput()->withErrors([
                'unit_id' => "No conversion factor exists for {$product->product_name} in that unit.",
            ]);
        }

        $crop->update([
            ...$data,
            'normalized_asking_price' => $normalized,
        ]);

        return redirect()->route('crops.index')->with('status', 'Crop updated.');
    }

    public function destroy(Request $request, Crop $crop): RedirectResponse
    {
        $this->authorizeOwner($request, $crop);

        $crop->delete();

        return redirect()->route('crops.index')->with('status', 'Crop removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'asking_price' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', Rule::in(['available', 'reserved', 'sold'])],
        ]);
    }

    private function authorizeOwner(Request $request, Crop $crop): void
    {
        // The farmer is the master of this row — nobody else edits it.
        abort_unless($crop->farmer_id === $request->user()->id, 403);
    }
}
