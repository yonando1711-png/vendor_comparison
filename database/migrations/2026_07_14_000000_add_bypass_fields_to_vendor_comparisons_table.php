<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            $table->unsignedBigInteger('bypassed_by')->nullable()->after('manager_notes');
            $table->timestamp('bypassed_at')->nullable()->after('bypassed_by');
            $table->text('bypass_reason')->nullable()->after('bypassed_at');

            $table->foreign('bypassed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            $table->dropForeign(['bypassed_by']);
            $table->dropColumn(['bypassed_by', 'bypassed_at', 'bypass_reason']);
        });
    }
};
