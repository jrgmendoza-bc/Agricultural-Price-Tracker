@extends('layouts.app')

@section('title', 'Report a Price')

@section('content')
<div class="mx-auto max-w-lg rounded-lg bg-white p-8 shadow">
    <h1 class="mb-1 text-2xl font-bold">Report a market price</h1>
    <p class="mb-6 text-sm text-stone-500">
        Record an observed commodity price at a market. The price is normalized
        to the product's base unit automatically.
    </p>

    <form method="POST" action="{{ route('reports.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="product_id" class="mb-1 block text-sm font-medium">Product</label>
            <select name="product_id" id="product_id" required class="w-full rounded border border-stone-300 px-3 py-2">
                <option value="">— select —</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                        {{ $product->product_name }} (base: {{ $product->baseUnit->code }})
                    </option>
                @endforeach
            </select>
            @error('product_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="market_id" class="mb-1 block text-sm font-medium">Market</label>
            <select name="market_id" id="market_id" required class="w-full rounded border border-stone-300 px-3 py-2">
                <option value="">— select —</option>
                @foreach ($markets as $market)
                    <option value="{{ $market->id }}" @selected(old('market_id') == $market->id)>
                        {{ $market->market_name }}
                    </option>
                @endforeach
            </select>
            @error('market_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="price" class="mb-1 block text-sm font-medium">Price (₱)</label>
                <input type="number" step="0.01" min="0.01" name="price" id="price" value="{{ old('price') }}" required
                       class="w-full rounded border border-stone-300 px-3 py-2">
                @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="unit_id" class="mb-1 block text-sm font-medium">Per unit</label>
                <select name="unit_id" id="unit_id" required class="w-full rounded border border-stone-300 px-3 py-2">
                    <option value="">— select —</option>
                    @foreach ($products as $product)
                        <optgroup label="{{ $product->product_name }}">
                            <option value="{{ $product->baseUnit->id }}" data-product="{{ $product->id }}" @selected(old('unit_id') == $product->baseUnit->id && old('product_id') == $product->id)>
                                {{ $product->baseUnit->code }} (base)
                            </option>
                            @foreach ($product->productUnits as $pu)
                                <option value="{{ $pu->unit->id }}" data-product="{{ $product->id }}" @selected(old('unit_id') == $pu->unit->id && old('product_id') == $product->id)>
                                    {{ $pu->unit->code }} (= {{ $pu->factor_to_base + 0 }} {{ $product->baseUnit->code }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('unit_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="date_reported" class="mb-1 block text-sm font-medium">Date observed</label>
            <input type="datetime-local" name="date_reported" id="date_reported"
                   value="{{ old('date_reported', now()->format('Y-m-d\TH:i')) }}" required
                   class="w-full rounded border border-stone-300 px-3 py-2">
            @error('date_reported') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="w-full rounded bg-green-700 py-2 font-semibold text-white hover:bg-green-600">
            Record report
        </button>
    </form>
</div>

<script>
    // Show only the units configured for the selected product.
    const productSelect = document.getElementById('product_id');
    const unitSelect = document.getElementById('unit_id');

    function filterUnits() {
        const productId = productSelect.value;
        let firstVisible = null;

        unitSelect.querySelectorAll('optgroup').forEach(group => {
            let anyVisible = false;
            group.querySelectorAll('option').forEach(opt => {
                const show = opt.dataset.product === productId;
                opt.hidden = !show;
                if (show) {
                    anyVisible = true;
                    firstVisible ??= opt;
                }
            });
            group.hidden = !anyVisible;
        });

        const current = unitSelect.selectedOptions[0];
        if (!current || current.hidden) {
            unitSelect.value = firstVisible ? firstVisible.value : '';
        }
    }

    productSelect.addEventListener('change', filterUnits);
    filterUnits();
</script>
@endsection
