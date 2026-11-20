<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // INSTANCE side: a farmer's physical batch of a product.
        // Farmer-owned, buyer-facing. Carries the farmer's OWN asking price,
        // distinct from the market price in price_reports.
        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete(); // the OWNER
            $table->foreignId('product_id')->constrained(); // "this batch is rice"
            $table->foreignId('unit_id')->constrained();    // unit quantity/asking_price use
            $table->decimal('quantity', 12, 2);
            $table->decimal('asking_price', 10, 2);
            $table->decimal('normalized_asking_price', 12, 4); // comparable to market reports
            $table->enum('status', ['available', 'reserved', 'sold'])->default('available');
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crops');
    }
};
