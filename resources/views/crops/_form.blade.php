{{-- Shared crop form fields. Expects $products; $crop is optional (edit). --}}
@php $editing = isset($crop) @endphp

<div>
    <label for="product_id" class="mb-1 block text-sm font-medium">Product</label>
    <select name="product_id" id="product_id" required class="w-full rounded border border-stone-300 px-3 py-2">
        <option value="">— select —</option>
        @foreach ($products as $product)
            <option value="{{ $product->id }}" @selected(old('product_id', $editing ? $crop->product_id : null) == $product->id)>
                {{ $product->product_name }} (base: {{ $product->baseUnit->code }})
            </option>
        @endforeach
    </select>
    @error('product_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-2 gap-3">
    <div>
        <label for="quantity" class="mb-1 block text-sm font-medium">Quantity</label>
        <input type="number" step="0.01" min="0.01" name="quantity" id="quantity"
               value="{{ old('quantity', $editing ? $crop->quantity + 0 : '') }}" required
               class="w-full rounded border border-stone-300 px-3 py-2">
        @error('quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="unit_id" class="mb-1 block text-sm font-medium">Unit</label>
        <select name="unit_id" id="unit_id" required class="w-full rounded border border-stone-300 px-3 py-2">
            <option value="">— select —</option>
            @foreach ($products as $product)
                <optgroup label="{{ $product->product_name }}">
                    <option value="{{ $product->baseUnit->id }}" data-product="{{ $product->id }}"
                            @selected(old('unit_id', $editing ? $crop->unit_id : null) == $product->baseUnit->id && old('product_id', $editing ? $crop->product_id : null) == $product->id)>
                        {{ $product->baseUnit->code }} (base)
                    </option>
                    @foreach ($product->productUnits as $pu)
                        <option value="{{ $pu->unit->id }}" data-product="{{ $product->id }}"
                                @selected(old('unit_id', $editing ? $crop->unit_id : null) == $pu->unit->id && old('product_id', $editing ? $crop->product_id : null) == $product->id)>
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
    <label for="asking_price" class="mb-1 block text-sm font-medium">Asking price (₱ per unit above)</label>
    <input type="number" step="0.01" min="0.01" name="asking_price" id="asking_price"
           value="{{ old('asking_price', $editing ? $crop->asking_price + 0 : '') }}" required
           class="w-full rounded border border-stone-300 px-3 py-2">
    @error('asking_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div>
    <label for="status" class="mb-1 block text-sm font-medium">Status</label>
    <select name="status" id="status" required class="w-full rounded border border-stone-300 px-3 py-2">
        @foreach (['available', 'reserved', 'sold'] as $status)
            <option value="{{ $status }}" @selected(old('status', $editing ? $crop->status : 'available') === $status)>
                {{ ucfirst($status) }}
            </option>
        @endforeach
    </select>
    @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
