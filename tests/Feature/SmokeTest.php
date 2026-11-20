<?php

namespace Tests\Feature;

use App\Models\Crop;
use App\Models\Market;
use App\Models\PriceReport;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    private function farmer(): User
    {
        return User::where('role', 'farmer')->firstOrFail();
    }

    private function buyer(): User
    {
        return User::where('role', 'buyer')->firstOrFail();
    }

    public function test_guest_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_all_authenticated_pages_render(): void
    {
        $rice = Product::where('product_name', 'Rice')->firstOrFail();
        $market = Market::firstOrFail();

        $this->actingAs($this->buyer());
        $this->get('/dashboard')->assertOk();
        $this->get('/products')->assertOk();
        $this->get("/products/{$rice->id}")->assertOk()->assertSee('30-day price trend');
        $this->get('/markets')->assertOk();
        $this->get("/markets/{$market->id}")->assertOk();
        $this->get('/reports')->assertOk();
        $this->get('/browse')->assertOk();
    }

    public function test_role_boundaries(): void
    {
        // Buyers cannot list crops or report prices.
        $this->actingAs($this->buyer());
        $this->get('/my-crops')->assertForbidden();
        $this->get('/reports/create')->assertForbidden();

        // Farmers manage crops but cannot report market prices.
        $this->actingAs($this->farmer());
        $this->get('/my-crops')->assertOk();
        $this->get('/my-crops/create')->assertOk();
        $this->get('/reports/create')->assertForbidden();

        // Admin reports prices.
        $this->actingAs($this->admin());
        $this->get('/reports/create')->assertOk();
    }

    public function test_price_report_normalizes_sack_to_kg(): void
    {
        $rice = Product::where('product_name', 'Rice')->firstOrFail();
        $market = Market::firstOrFail();
        $sack = $rice->productUnits()->with('unit')->get()
            ->firstWhere('unit.code', 'sack');

        $this->actingAs($this->admin())
            ->post('/reports', [
                'product_id' => $rice->id,
                'market_id' => $market->id,
                'unit_id' => $sack->unit_id,
                'price' => 2500.00, // per 50-kg sack
                'date_reported' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect('/reports');

        $report = PriceReport::latest('id')->first();
        $this->assertEquals(50.0, (float) $report->normalized_price); // 2500 / 50
        $this->assertSame('manual', $report->source_type);
    }

    public function test_report_in_unconfigured_unit_is_rejected(): void
    {
        $coconut = Product::where('product_name', 'Coconut')->firstOrFail();
        $rice = Product::where('product_name', 'Rice')->firstOrFail();
        $sackUnitId = $rice->productUnits()->with('unit')->get()
            ->firstWhere('unit.code', 'sack')->unit_id;

        // Coconut has no sack conversion — must be rejected, not guessed.
        $this->actingAs($this->admin())
            ->post('/reports', [
                'product_id' => $coconut->id,
                'market_id' => Market::first()->id,
                'unit_id' => $sackUnitId,
                'price' => 500.00,
                'date_reported' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasErrors('unit_id');
    }

    public function test_farmer_lists_a_crop_with_normalized_ask(): void
    {
        $corn = Product::where('product_name', 'Corn')->firstOrFail();
        $sack = $corn->productUnits()->with('unit')->get()
            ->firstWhere('unit.code', 'sack'); // 1 sack corn = 57 kg

        $farmer = $this->farmer();

        $this->actingAs($farmer)
            ->post('/my-crops', [
                'product_id' => $corn->id,
                'unit_id' => $sack->unit_id,
                'quantity' => 10,
                'asking_price' => 1425.00,
                'status' => 'available',
            ])
            ->assertRedirect('/my-crops');

        $crop = Crop::latest('id')->first();
        $this->assertSame($farmer->id, $crop->farmer_id);
        $this->assertEquals(25.0, (float) $crop->normalized_asking_price); // 1425 / 57
    }

    public function test_farmer_cannot_edit_someone_elses_crop(): void
    {
        $otherFarmersCrop = Crop::whereHas('farmer')->firstOrFail();
        $intruder = User::factory()->farmer()->create();

        $this->actingAs($intruder);
        $this->get("/my-crops/{$otherFarmersCrop->id}/edit")->assertForbidden();
        $this->delete("/my-crops/{$otherFarmersCrop->id}")->assertForbidden();
    }
}
