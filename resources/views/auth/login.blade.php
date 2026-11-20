@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="mx-auto mt-10 max-w-md rounded-lg bg-white p-8 shadow">
    <h1 class="mb-6 text-2xl font-bold">Log in</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded border border-stone-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input type="password" name="password" id="password" required
                   class="w-full rounded border border-stone-300 px-3 py-2">
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>

        <button type="submit" class="w-full rounded bg-green-700 py-2 font-semibold text-white hover:bg-green-600">
            Log in
        </button>
    </form>

    <p class="mt-4 text-sm text-stone-500">
        No account yet? <a href="{{ route('register') }}" class="text-green-700 underline">Register</a>
    </p>

    <div class="mt-6 rounded bg-stone-50 p-3 text-xs text-stone-500">
        <p class="font-semibold">Demo accounts (password: <code>password</code>)</p>
        <p>admin@agriprice.test · khaya@agriprice.test (farmer) · buyer@agriprice.test</p>
    </div>
</div>
@endsection
