@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="mx-auto mt-10 max-w-md rounded-lg bg-white p-8 shadow">
    <h1 class="mb-6 text-2xl font-bold">Create an account</h1>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="first_name" class="mb-1 block text-sm font-medium">First name</label>
                <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required
                       class="w-full rounded border border-stone-300 px-3 py-2">
                @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="last_name" class="mb-1 block text-sm font-medium">Last name</label>
                <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required
                       class="w-full rounded border border-stone-300 px-3 py-2">
                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="w-full rounded border border-stone-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phone_number" class="mb-1 block text-sm font-medium">Phone number (optional)</label>
            <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number') }}"
                   class="w-full rounded border border-stone-300 px-3 py-2">
            @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">I am a</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer items-center gap-2 rounded border border-stone-300 px-3 py-2 has-[:checked]:border-green-600 has-[:checked]:bg-green-50">
                    <input type="radio" name="role" value="farmer" @checked(old('role') === 'farmer')> 🧑‍🌾 Farmer (seller)
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded border border-stone-300 px-3 py-2 has-[:checked]:border-green-600 has-[:checked]:bg-green-50">
                    <input type="radio" name="role" value="buyer" @checked(old('role', 'buyer') === 'buyer')> 🛒 Buyer
                </label>
            </div>
            @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium">Password</label>
            <input type="password" name="password" id="password" required
                   class="w-full rounded border border-stone-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                   class="w-full rounded border border-stone-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded bg-green-700 py-2 font-semibold text-white hover:bg-green-600">
            Register
        </button>
    </form>

    <p class="mt-4 text-sm text-stone-500">
        Already registered? <a href="{{ route('login') }}" class="text-green-700 underline">Log in</a>
    </p>
</div>
@endsection
