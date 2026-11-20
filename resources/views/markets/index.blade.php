@extends('layouts.app')

@section('title', 'Markets')

@section('content')
<h1 class="mb-2 text-2xl font-bold">Markets</h1>
<p class="mb-6 text-sm text-stone-500">Places where prices are observed.</p>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($markets as $market)
        <a href="{{ route('markets.show', $market) }}" class="rounded-lg bg-white p-4 shadow transition hover:shadow-md">
            <h2 class="font-semibold">{{ $market->market_name }}</h2>
            <p class="mt-1 text-sm text-stone-500">📍 {{ $market->location }}</p>
            <p class="mt-2 text-xs text-stone-400">
                {{ $market->price_reports_count }} {{ Str::plural('price report', $market->price_reports_count) }}
            </p>
        </a>
    @endforeach
</div>
@endsection
