@extends('layouts.app')

@section('title', 'Products')

@section('content')
<h1 class="mb-6 text-2xl font-bold">Products</h1>

<div class="overflow-x-auto rounded-lg bg-white shadow">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2">Category</th>
                <th class="px-4 py-2">Base unit</th>
                <th class="px-4 py-2">Total reports</th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            @foreach ($products as $product)
                <tr>
                    <td class="px-4 py-2 font-medium">{{ $product->product_name }}</td>
                    <td class="px-4 py-2">{{ $product->category }}</td>
                    <td class="px-4 py-2">{{ $product->baseUnit->name }} ({{ $product->baseUnit->code }})</td>
                    <td class="px-4 py-2">{{ $product->price_reports_count }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('products.show', $product) }}" class="text-green-700 underline">View trends</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
