<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            $table->unsignedBigInteger('controller_id')->nullable()->after('manager_notes');
            $table->timestamp('controller_acknowledged_at')->nullable()->after('controller_id');
            $table->text('controller_notes')->nullable()->after('controller_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_comparisons', function (Blueprint $table) {
            $table->dropColumn(['controller_id', 'controller_acknowledged_at', 'controller_notes']);
        });
    }
};
