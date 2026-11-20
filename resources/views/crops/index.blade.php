@extends('layouts.app')

@section('title', 'My Crops')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">My crops</h1>
    <a href="{{ route('crops.create') }}" class="rounded bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">
        + List a crop
    </a>
</div>

<div class="overflow-x-auto rounded-lg bg-white shadow">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2">Quantity</th>
                <th class="px-4 py-2">Asking price</th>
                <th class="px-4 py-2">Normalized</th>
                <th class="px-4 py-2">vs market</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            @forelse ($crops as $crop)
                @php $diff = $crop->vsMarketRate() @endphp
                <tr>
                    <td class="px-4 py-2 font-medium">{{ $crop->product->product_name }}</td>
                    <td class="px-4 py-2">{{ $crop->quantity + 0 }} {{ $crop->unit->code }}</td>
                    <td class="px-4 py-2">₱{{ number_format($crop->asking_price, 2) }}/{{ $crop->unit->code }}</td>
                    <td class="px-4 py-2">₱{{ number_format($crop->normalized_asking_price, 2) }}/{{ $crop->product->baseUnit->code }}</td>
                    <td class="px-4 py-2">
                        @if ($diff === null)
                            <span class="text-stone-400">—</span>
                        @elseif ($diff > 0)
                            <span class="text-red-600">+{{ number_format($diff, 1) }}%</span>
                        @else
                            <span class="text-green-600">−{{ number_format(abs($diff), 1) }}%</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <span class="rounded px-2 py-0.5 text-xs
                            {{ $crop->status === 'available' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $crop->status === 'reserved' ? 'bg-amber-100 text-amber-800' : '' }}
                            {{ $crop->status === 'sold' ? 'bg-stone-200 text-stone-600' : '' }}">
                            {{ $crop->status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('crops.edit', $crop) }}" class="text-green-700 underline">Edit</a>
                        <form method="POST" action="{{ route('crops.destroy', $crop) }}" class="inline"
                              onsubmit="return confirm('Remove this crop listing?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ml-2 text-red-600 underline">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-stone-400">You have no crop listings yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
