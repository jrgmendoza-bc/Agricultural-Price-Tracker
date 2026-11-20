<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AgriPrice') — AgriPrice Carmen</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="min-h-screen bg-stone-100 text-stone-800">
    <nav class="bg-green-800 text-white shadow">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('dashboard') }}" class="text-lg font-bold tracking-tight">
                🌾 AgriPrice <span class="font-normal text-green-200">Carmen</span>
            </a>
            @auth
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('dashboard') }}" class="hover:text-green-200">Dashboard</a>
                    <a href="{{ route('products.index') }}" class="hover:text-green-200">Products</a>
                    <a href="{{ route('markets.index') }}" class="hover:text-green-200">Markets</a>
                    <a href="{{ route('reports.index') }}" class="hover:text-green-200">Price Reports</a>
                    <a href="{{ route('crops.browse') }}" class="hover:text-green-200">Browse Crops</a>
                    @if (auth()->user()->isFarmer())
                        <a href="{{ route('crops.index') }}" class="hover:text-green-200">My Crops</a>
                    @endif
                    {{-- @if (auth()->user()->isAdmin()) --}}
                        <a href="{{ route('reports.create') }}" class="rounded bg-green-600 px-2 py-1 hover:bg-green-500">+ Report Price</a>
                    {{-- @endif --}}
                    <span class="hidden text-green-200 sm:inline">
                        {{ auth()->user()->full_name }} ({{ auth()->user()->role }})
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded border border-green-500 px-2 py-1 hover:bg-green-700">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-green-300 bg-green-50 px-4 py-2 text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
