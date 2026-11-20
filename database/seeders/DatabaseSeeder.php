<?php

namespace Database\Seeders;

use App\Models\Market;
use App\Models\PriceReport;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Units --------------------------------------------------
        $units = collect([
            ['code' => 'kg', 'name' => 'Kilogram'],
            ['code' => 'sack', 'name' => 'Sack'],
            ['code' => 'ton', 'name' => 'Metric Ton'],
            ['code' => 'pc', 'name' => 'Piece'],
            ['code' => 'crate', 'name' => 'Crate'],
            ['code' => 'bundle', 'name' => 'Bundle'],
        ])->mapWithKeys(fn ($u) => [$u['code'] => Unit::create($u)]);

        // ---- Products (the type hub) + per-product conversions ------
        // factor_to_base: 1 unit = X base units. A sack of rice is 50 kg;
        // a sack of corn is 57 kg — that's why factors live per product.
        $catalog = [
            ['name' => 'Rice', 'category' => 'Grains', 'base' => 'kg', 'conversions' => ['sack' => 50, 'ton' => 1000], 'price' => 48.0],
            ['name' => 'Corn', 'category' => 'Grains', 'base' => 'kg', 'conversions' => ['sack' => 57, 'ton' => 1000], 'price' => 25.0],
            ['name' => 'Coconut', 'category' => 'Fruits', 'base' => 'pc', 'conversions' => [], 'price' => 15.0],
            ['name' => 'Tomato', 'category' => 'Vegetables', 'base' => 'kg', 'conversions' => ['crate' => 25], 'price' => 60.0],
            ['name' => 'Eggplant', 'category' => 'Vegetables', 'base' => 'kg', 'conversions' => ['bundle' => 5], 'price' => 45.0],
            ['name' => 'Sugarcane', 'category' => 'Cash Crops', 'base' => 'kg', 'conversions' => ['ton' => 1000], 'price' => 3.5],
        ];

        $products = [];
        $basePrices = [];
        foreach ($catalog as $entry) {
            $product = Product::create([
                'product_name' => $entry['name'],
                'category' => $entry['category'],
                'base_unit_id' => $units[$entry['base']]->id,
            ]);

            foreach ($entry['conversions'] as $code => $factor) {
                ProductUnit::create([
                    'product_id' => $product->id,
                    'unit_id' => $units[$code]->id,
                    'factor_to_base' => $factor,
                    'is_default' => false,
                ]);
            }

            $products[] = $product;
            $basePrices[$product->id] = $entry['price'];
        }

        // ---- Markets (places, not actors) ----------------------------
        $markets = collect([
            ['market_name' => 'Carmen Public Market', 'location' => 'Poblacion, Carmen', 'lat' => 7.3617, 'lng' => 125.7061],
            ['market_name' => 'Ising Market', 'location' => 'Ising, Carmen', 'lat' => 7.3410, 'lng' => 125.6889],
            ['market_name' => 'Mabuhay Trading Post', 'location' => 'Mabuhay, Carmen', 'lat' => 7.3702, 'lng' => 125.7194],
        ])->map(fn ($m) => Market::create($m));

        // ---- Users ----------------------------------------------------
        $admin = User::create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'admin@agriprice.test',
            'phone_number' => '09170000001',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $khaya = User::create([
            'first_name' => 'Khaya',
            'last_name' => 'Santos',
            'email' => 'khaya@agriprice.test',
            'phone_number' => '09170000002',
            'password' => 'password',
            'role' => 'farmer',
        ]);

        $rynna = User::create([
            'first_name' => 'Rynna',
            'last_name' => 'Dela Cruz',
            'email' => 'rynna@agriprice.test',
            'phone_number' => '09170000003',
            'password' => 'password',
            'role' => 'farmer',
        ]);

        User::create([
            'first_name' => 'Ben',
            'last_name' => 'Uy',
            'email' => 'buyer@agriprice.test',
            'phone_number' => '09170000004',
            'password' => 'password',
            'role' => 'buyer',
        ]);

        // ---- 30 days of price reports --------------------------------
        // Random walk around each product's base price; occasionally
        // reported in a non-base unit to exercise normalization.
        foreach ($products as $product) {
            $conversions = ProductUnit::where('product_id', $product->id)->with('unit')->get();

            foreach ($markets as $market) {
                // Each market drifts slightly off the base price.
                $current = $basePrices[$product->id] * (1 + (mt_rand(-8, 8) / 100));

                for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
                    // ~70% of days get a report per product/market.
                    if (mt_rand(1, 100) > 70 && $daysAgo !== 0) {
                        continue;
                    }

                    $current *= 1 + (mt_rand(-3, 3) / 100); // daily drift ±3%
                    $normalized = round($current, 4);

                    // Every so often, report in a converted unit instead.
                    $useConversion = $conversions->isNotEmpty() && mt_rand(1, 100) <= 25;
                    if ($useConversion) {
                        $conv = $conversions->random();
                        $unitId = $conv->unit_id;
                        $rawPrice = round($normalized * (float) $conv->factor_to_base, 2);
                    } else {
                        $unitId = $product->base_unit_id;
                        $rawPrice = round($normalized, 2);
                    }

                    PriceReport::create([
                        'product_id' => $product->id,
                        'market_id' => $market->id,
                        'unit_id' => $unitId,
                        'price' => $rawPrice,
                        'normalized_price' => $product->normalizePrice($rawPrice, $unitId),
                        'source_type' => mt_rand(1, 100) <= 60 ? 'bulletin' : 'manual',
                        'reported_by' => $admin->id,
                        'date_reported' => now()->subDays($daysAgo)->setTime(mt_rand(6, 11), mt_rand(0, 59)),
                    ]);
                }
            }
        }

        // ---- price_history: reuse the real aggregation command -------
        for ($daysAgo = 30; $daysAgo >= 0; $daysAgo--) {
            Artisan::call('prices:snapshot', ['date' => now()->subDays($daysAgo)->toDateString()]);
        }

        // ---- Crops (farmer-owned instances with asking prices) -------
        $rice = $products[0];
        $corn = $products[1];
        $tomato = $products[3];

        $khaya->crops()->create([
            'product_id' => $rice->id,
            'unit_id' => $units['kg']->id,
            'quantity' => 200,
            'asking_price' => 48.00,
            'normalized_asking_price' => $rice->normalizePrice(48.00, $units['kg']->id),
            'status' => 'available',
        ]);

        $khaya->crops()->create([
            'product_id' => $tomato->id,
            'unit_id' => $units['crate']->id,
            'quantity' => 12,
            'asking_price' => 1400.00, // per 25-kg crate → ₱56/kg
            'normalized_asking_price' => $tomato->normalizePrice(1400.00, $units['crate']->id),
            'status' => 'available',
        ]);

        $rynna->crops()->create([
            'product_id' => $corn->id,
            'unit_id' => $units['sack']->id,
            'quantity' => 20,
            'asking_price' => 1450.00, // per 57-kg sack → ~₱25.4/kg
            'normalized_asking_price' => $corn->normalizePrice(1450.00, $units['sack']->id),
            'status' => 'available',
        ]);

        $rynna->crops()->create([
            'product_id' => $rice->id,
            'unit_id' => $units['sack']->id,
            'quantity' => 5,
            'asking_price' => 2350.00, // per 50-kg sack → ₱47/kg
            'normalized_asking_price' => $rice->normalizePrice(2350.00, $units['sack']->id),
            'status' => 'reserved',
        ]);
    }
}
