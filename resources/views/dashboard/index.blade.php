@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<h1 class="mb-1 text-2xl font-bold">Market Dashboard</h1>
<p class="mb-6 text-sm text-stone-500">
    Current rates are 7-day averages of normalized prices across {{ $marketCount }} {{ Str::plural('market', $marketCount) }}.
</p>

{{-- Current market rates per product --}}
<div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($products as $product)
        @php $rate = $rates->get($product->id) @endphp
        <a href="{{ route('products.show', $product) }}"
           class="rounded-lg bg-white p-4 shadow transition hover:shadow-md">
            <div class="flex items-baseline justify-between">
                <h2 class="font-semibold">{{ $product->product_name }}</h2>
                <span class="rounded bg-stone-100 px-2 py-0.5 text-xs text-stone-500">{{ $product->category }}</span>
            </div>
            @if ($rate)
                <p class="mt-2 text-2xl font-bold text-green-700">
                    ₱{{ number_format($rate->avg_price, 2) }}
                    <span class="text-sm font-normal text-stone-500">/ {{ $product->baseUnit->code }}</span>
                </p>
                <p class="mt-1 text-xs text-stone-500">
                    range ₱{{ number_format($rate->min_price, 2) }}–₱{{ number_format($rate->max_price, 2) }}
                    · {{ $rate->report_count }} {{ Str::plural('report', $rate->report_count) }} this week
                </p>
            @else
                <p class="mt-2 text-sm text-stone-400">No reports in the last 7 days.</p>
            @endif
        </a>
    @endforeach
</div>

{{-- Farmer: my asking prices vs the market --}}
@if ($myCrops !== null && $myCrops->isNotEmpty())
    <div class="mb-8">
        <h2 class="mb-3 text-lg font-semibold">My asking prices vs the market</h2>
        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-stone-500">
                    <tr>
                        <th class="px-4 py-2">Crop</th>
                        <th class="px-4 py-2">My ask (normalized)</th>
                        <th class="px-4 py-2">Market rate</th>
                        <th class="px-4 py-2">Difference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($myCrops as $crop)
                        @php
                            $diff = $crop->vsMarketRate();
                            $marketRate = $crop->product->currentMarketRate();
                        @endphp
                        <tr>
                            <td class="px-4 py-2 font-medium">
                                {{ $crop->product->product_name }}
                                <span class="text-stone-400">({{ $crop->quantity + 0 }} {{ $crop->unit->code }})</span>
                            </td>
                            <td class="px-4 py-2">₱{{ number_format($crop->normalized_asking_price, 2) }}/{{ $crop->product->baseUnit->code }}</td>
                            <td class="px-4 py-2">
                                {{ $marketRate !== null ? '₱' . number_format($marketRate, 2) . '/' . $crop->product->baseUnit->code : '—' }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($diff === null)
                                    <span class="text-stone-400">no market data</span>
                                @elseif ($diff > 0)
                                    <span class="font-medium text-red-600">{{ number_format($diff, 1) }}% above market</span>
                                @else
                                    <span class="font-medium text-green-600">{{ number_format(abs($diff), 1) }}% below market</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- Latest reports --}}
<h2 class="mb-3 text-lg font-semibold">Latest price reports</h2>
<div class="overflow-x-auto rounded-lg bg-white shadow">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-2">Date</th>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2">Market</th>
                <th class="px-4 py-2">Reported price</th>
                <th class="px-4 py-2">Normalized</th>
                <th class="px-4 py-2">Source</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            @foreach ($latestReports as $report)
                <tr>
                    <td class="px-4 py-2 text-stone-500">{{ $report->date_reported->format('M j, g:i A') }}</td>
                    <td class="px-4 py-2 font-medium">{{ $report->product->product_name }}</td>
                    <td class="px-4 py-2">{{ $report->market->market_name }}</td>
                    <td class="px-4 py-2">₱{{ number_format($report->price, 2) }}/{{ $report->unit->code }}</td>
                    <td class="px-4 py-2 text-green-700">₱{{ number_format($report->normalized_price, 2) }}/{{ $report->product->baseUnit->code }}</td>
                    <td class="px-4 py-2"><span class="rounded bg-stone-100 px-2 py-0.5 text-xs">{{ $report->source_type }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
