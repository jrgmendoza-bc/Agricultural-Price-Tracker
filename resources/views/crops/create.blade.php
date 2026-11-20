@extends('layouts.app')

@section('title', 'List a Crop')

@section('content')
<div class="mx-auto max-w-lg rounded-lg bg-white p-8 shadow">
    <h1 class="mb-1 text-2xl font-bold">List a crop</h1>
    <p class="mb-6 text-sm text-stone-500">
        Your batch of a product, at your asking price. Buyers see it compared
        against the going market rate.
    </p>

    <form method="POST" action="{{ route('crops.store') }}" class="space-y-4">
        @csrf
        @include('crops._form')
        <button type="submit" class="w-full rounded bg-green-700 py-2 font-semibold text-white hover:bg-green-600">
            List crop
        </button>
    </form>
</div>
@endsection
