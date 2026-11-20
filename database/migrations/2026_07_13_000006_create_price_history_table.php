<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One aggregated row per product/market/day — feeds the trend charts.
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('avg_normalized_price', 12, 4);
            $table->decimal('min_normalized_price', 12, 4);
            $table->decimal('max_normalized_price', 12, 4);
            $table->timestamps();

            $table->unique(['product_id', 'market_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
