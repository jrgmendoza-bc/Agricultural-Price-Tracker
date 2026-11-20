<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OBSERVATION side: a commodity's rate at a place.
        // Owned by nobody personal — it's a fact about the market.
        Schema::create('price_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained(); // unit as reported — never discarded
            $table->decimal('price', 10, 2);             // raw reported price
            $table->decimal('normalized_price', 12, 4);  // ₱ per base_unit — enables comparison
            $table->enum('source_type', ['scraped', 'bulletin', 'manual'])->default('manual');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete(); // null when scraped
            $table->dateTime('date_reported');
            $table->timestamps();

            $table->index(['product_id', 'market_id', 'date_reported']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_reports');
    }
};
