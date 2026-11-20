<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The TYPE hub: one row per commodity ("Rice", "Corn").
        // Everything else (price reports, history, crops) FKs into this.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name')->unique();
            $table->string('category'); // "Grains" | "Vegetables" — a value, not a table
            $table->foreignId('base_unit_id')->constrained('units'); // canonical unit prices normalize to
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
