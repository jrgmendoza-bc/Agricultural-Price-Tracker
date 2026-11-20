@extends('layouts.app')

@section('title', 'Price Reports')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Price Reports</h1>
    {{-- @if (auth()->user()->isAdmin()) --}}
        <a href="{{ route('reports.create') }}" class="rounded bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">
            + Report a price
        </a>
    {{-- @endif --}}
</div>

<div class="overflow-x-auto rounded-lg bg-white shadow">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-2">Date</th>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2">Market</th>
                <th class="px-4 py-2">As reported</th>
                <th class="px-4 py-2">Normalized</th>
                <th class="px-4 py-2">Source</th>
                <th class="px-4 py-2">Reported by</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            @foreach ($reports as $report)
                <tr>
                    <td class="px-4 py-2 text-stone-500">{{ $report->date_reported->format('M j, Y g:i A') }}</td>
                    <td class="px-4 py-2 font-medium">{{ $report->product->product_name }}</td>
                    <td class="px-4 py-2">{{ $report->market->market_name }}</td>
                    <td class="px-4 py-2">₱{{ number_format($report->price, 2) }}/{{ $report->unit->code }}</td>
                    <td class="px-4 py-2 text-green-700">₱{{ number_format($report->normalized_price, 2) }}/{{ $report->product->baseUnit->code }}</td>
                    <td class="px-4 py-2"><span class="rounded bg-stone-100 px-2 py-0.5 text-xs">{{ $report->source_type }}</span></td>
                    <td class="px-4 py-2 text-stone-500">{{ $report->reporter?->full_name ?? 'System' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $reports->links() }}
</div>
@endsection
