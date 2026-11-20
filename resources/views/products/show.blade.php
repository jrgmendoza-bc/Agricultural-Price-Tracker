@extends('layouts.app')

@section('title', $product->product_name)

@section('content')
<div class="mb-6 flex items-baseline justify-between">
    <div>
        <h1 class="text-2xl font-bold">{{ $product->product_name }}</h1>
        <p class="text-sm text-stone-500">
            {{ $product->category }} · prices normalized to ₱ per {{ $product->baseUnit->code }}
        </p>
    </div>
    @if ($marketRate !== null)
        <div class="text-right">
            <p class="text-sm text-stone-500">Current market rate (7-day avg)</p>
            <p class="text-3xl font-bold text-green-700">₱{{ number_format($marketRate, 2) }}<span class="text-base font-normal text-stone-500">/{{ $product->baseUnit->code }}</span></p>
        </div>
    @endif
</div>

{{-- Units & conversions --}}
@if ($product->productUnits->isNotEmpty())
    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 text-sm font-semibold text-stone-500">Unit conversions for this product</h2>
        <div class="flex flex-wrap gap-3 text-sm">
            @foreach ($product->productUnits as $pu)
                <span class="rounded bg-stone-100 px-3 py-1">
                    1 {{ $pu->unit->code }} = {{ $pu->factor_to_base + 0 }} {{ $product->baseUnit->code }}
                </span>
            @endforeach
        </div>
    </div>
@endif

{{-- 30-day trend chart --}}
<div class="mb-8 rounded-lg bg-white p-4 shadow">
    <h2 class="mb-3 text-lg font-semibold">30-day price trend by market</h2>
    @if ($chartSeries->isEmpty())
        <p class="text-sm text-stone-400">No history yet for this product.</p>
    @else
        <canvas id="trendChart" height="90"></canvas>
    @endif
</div>

<div class="grid gap-8 lg:grid-cols-2">
    {{-- Recent reports --}}
    <div>
        <h2 class="mb-3 text-lg font-semibold">Recent reports</h2>
        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-stone-500">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Market</th>
                        <th class="px-3 py-2">As reported</th>
                        <th class="px-3 py-2">Normalized</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($recentReports as $report)
                        <tr>
                            <td class="px-3 py-2 text-stone-500">{{ $report->date_reported->format('M j') }}</td>
                            <td class="px-3 py-2">{{ $report->market->market_name }}</td>
                            <td class="px-3 py-2">₱{{ number_format($report->price, 2) }}/{{ $report->unit->code }}</td>
                            <td class="px-3 py-2 text-green-700">₱{{ number_format($report->normalized_price, 2) }}/{{ $product->baseUnit->code }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-stone-400">No reports yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Available crops from farmers --}}
    <div>
        <h2 class="mb-3 text-lg font-semibold">Available from farmers</h2>
        <div class="space-y-3">
            @forelse ($availableCrops as $crop)
                @php $diff = $crop->vsMarketRate() @endphp
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="flex items-baseline justify-between">
                        <p class="font-medium">{{ $crop->farmer->full_name }}</p>
                        <p class="font-bold text-green-700">₱{{ number_format($crop->asking_price, 2) }}/{{ $crop->unit->code }}</p>
                    </div>
                    <p class="mt-1 text-sm text-stone-500">
                        {{ $crop->quantity + 0 }} {{ $crop->unit->code }} available
                        · ₱{{ number_format($crop->normalized_asking_price, 2) }}/{{ $product->baseUnit->code }} normalized
                        @if ($diff !== null)
                            ·
                            @if ($diff > 0)
                                <span class="text-red-600">{{ number_format($diff, 1) }}% above market</span>
                            @else
                                <span class="text-green-600">{{ number_format(abs($diff), 1) }}% below market</span>
                            @endif
                        @endif
                    </p>
                    @if ($crop->farmer->phone_number)
                        <p class="mt-1 text-sm text-stone-500">📞 {{ $crop->farmer->phone_number }}</p>
                    @endif
                </div>
            @empty
                <p class="rounded-lg bg-white p-4 text-sm text-stone-400 shadow">No farmers currently listing this product.</p>
            @endforelse
        </div>
    </div>
</div>

@if (! $chartSeries->isEmpty())
<script>
    const series = @json($chartSeries);
    const palette = ['#15803d', '#b45309', '#1d4ed8', '#be185d', '#0f766e'];

    const allDates = [...new Set(Object.values(series).flatMap(rows => rows.map(r => r.date)))].sort();

    const datasets = Object.entries(series).map(([market, rows], i) => {
        const byDate = Object.fromEntries(rows.map(r => [r.date, r.avg]));
        return {
            label: market,
            data: allDates.map(d => byDate[d] ?? null),
            borderColor: palette[i % palette.length],
            backgroundColor: palette[i % palette.length],
            spanGaps: true,
            tension: 0.3,
            pointRadius: 2,
        };
    });

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: { labels: allDates, datasets },
        options: {
            scales: {
                y: {
                    title: { display: true, text: '₱ per {{ $product->baseUnit->code }}' },
                },
            },
            interaction: { mode: 'index', intersect: false },
        },
    });
</script>
@endif
@endsection
