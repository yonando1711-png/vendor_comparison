<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_id')->constrained('vendor_comparisons')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');          // submitted, approved, rejected, edited, etc.
            $table->text('notes')->nullable(); // extra context
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_logs');
    }
};
