@extends('layouts.app')

@section('title', 'Browse Crops')

@section('content')
<h1 class="mb-1 text-2xl font-bold">Browse crops for sale</h1>
<p class="mb-6 text-sm text-stone-500">
    Farmers' available batches, cheapest normalized asking price first.
</p>

<form method="GET" class="mb-6 flex items-center gap-3">
    <select name="product_id" class="rounded border border-stone-300 px-3 py-2 text-sm">
        <option value="">All products</option>
        @foreach ($products as $product)
            <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                {{ $product->product_name }}
            </option>
        @endforeach
    </select>
    <button type="submit" class="rounded bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">Filter</button>
    @if (request('product_id'))
        <a href="{{ route('crops.browse') }}" class="text-sm text-stone-500 underline">clear</a>
    @endif
</form>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($crops as $crop)
        @php $diff = $crop->vsMarketRate() @endphp
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="flex items-baseline justify-between">
                <h2 class="font-semibold">{{ $crop->product->product_name }}</h2>
                <span class="rounded bg-stone-100 px-2 py-0.5 text-xs text-stone-500">{{ $crop->product->category }}</span>
            </div>
            <p class="mt-2 text-2xl font-bold text-green-700">
                ₱{{ number_format($crop->asking_price, 2) }}
                <span class="text-sm font-normal text-stone-500">/ {{ $crop->unit->code }}</span>
            </p>
            <p class="text-xs text-stone-500">
                ₱{{ number_format($crop->normalized_asking_price, 2) }}/{{ $crop->product->baseUnit->code }} normalized
                @if ($diff !== null)
                    ·
                    @if ($diff > 0)
                        <span class="text-red-600">{{ number_format($diff, 1) }}% above market</span>
                    @else
                        <span class="text-green-600">{{ number_format(abs($diff), 1) }}% below market</span>
                    @endif
                @endif
            </p>
            <div class="mt-3 border-t border-stone-100 pt-3 text-sm">
                <p>{{ $crop->quantity + 0 }} {{ $crop->unit->code }} available</p>
                <p class="text-stone-500">🧑‍🌾 {{ $crop->farmer->full_name }}</p>
                @if ($crop->farmer->phone_number)
                    <p class="text-stone-500">📞 {{ $crop->farmer->phone_number }}</p>
                @endif
            </div>
        </div>
    @empty
        <p class="rounded-lg bg-white p-6 text-stone-400 shadow sm:col-span-2 lg:col-span-3">
            No crops currently available{{ request('product_id') ? ' for this product' : '' }}.
        </p>
    @endforelse
</div>
@endsection
