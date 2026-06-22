<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the unique constraint on po_id so that multiple comparisons
     * can exist for the same RFQ (e.g. after a rejection and resubmission).
     * The application-level guard in ComparisonController::store() already
     * prevents duplicate active comparisons per RFQ.
     */
    public function up(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            $table->dropUnique(['po_id']);
        });
    }

    public function down(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            $table->unique('po_id');
        });
    }
};
