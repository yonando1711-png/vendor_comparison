<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            // Purchase category (Unit Baru / Aksesoris Mobil / Sparepart / Umum)
            $table->string('category')->nullable()->after('po_vendor');
            // Per-product × per-vendor price matrix stored as JSON
            $table->json('vendor_prices')->nullable()->after('vendors');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            $table->dropColumn(['category', 'vendor_prices']);
        });
    }
};
