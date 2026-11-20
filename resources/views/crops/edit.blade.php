@extends('layouts.app')

@section('title', 'Edit Crop')

@section('content')
<div class="mx-auto max-w-lg rounded-lg bg-white p-8 shadow">
    <h1 class="mb-6 text-2xl font-bold">Edit crop listing</h1>

    <form method="POST" action="{{ route('crops.update', $crop) }}" class="space-y-4">
        @csrf
        @method('PUT')
        @include('crops._form')
        <button type="submit" class="w-full rounded bg-green-700 py-2 font-semibold text-white hover:bg-green-600">
            Save changes
        </button>
    </form>
</div>
@endsection
