@extends('layouts.app')

@section('title', $market->market_name)

@section('content')
<h1 class="mb-1 text-2xl font-bold">{{ $market->market_name }}</h1>
<p class="mb-6 text-sm text-stone-500">
    📍 {{ $market->location }}
    @if ($market->lat && $market->lng)
        · <a class="text-green-700 underline" target="_blank" rel="noopener"
             href="https://www.openstreetmap.org/?mlat={{ $market->lat }}&mlon={{ $market->lng }}#map=15/{{ $market->lat }}/{{ $market->lng }}">view on map</a>
    @endif
</p>

<h2 class="mb-3 text-lg font-semibold">Latest price per product at this market</h2>
<div class="overflow-x-auto rounded-lg bg-white shadow">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2">As reported</th>
                <th class="px-4 py-2">Normalized</th>
                <th class="px-4 py-2">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            @forelse ($latestPerProduct as $report)
                <tr>
                    <td class="px-4 py-2 font-medium">
                        <a href="{{ route('products.show', $report->product) }}" class="text-green-700 hover:underline">
                            {{ $report->product->product_name }}
                        </a>
                    </td>
                    <td class="px-4 py-2">₱{{ number_format($report->price, 2) }}/{{ $report->unit->code }}</td>
                    <td class="px-4 py-2 text-green-700">₱{{ number_format($report->normalized_price, 2) }}/{{ $report->product->baseUnit->code }}</td>
                    <td class="px-4 py-2 text-stone-500">{{ $report->date_reported->format('M j, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-4 text-stone-400">No reports at this market yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
