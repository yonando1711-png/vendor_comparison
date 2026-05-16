<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_comparisons', function (Blueprint $table) {
            $table->id();

            // Odoo reference
            $table->unsignedInteger('po_id');        // purchase.order ID in Odoo
            $table->string('po_name');               // e.g. 2026/POO/01333
            $table->string('po_vendor')->nullable(); // vendor display name
            $table->string('selected_vendor')->nullable(); // vendor chosen in comparison
            $table->text('notes')->nullable();       // creator notes

            // Workflow status
            $table->enum('status', [
                'draft',              // just submitted by creator
                'pending_supervisor', // waiting for supervisor
                'pending_manager',    // supervisor approved, waiting for manager
                'approved',           // fully approved
                'rejected',           // rejected at any stage
            ])->default('pending_supervisor');

            // Creator
            $table->foreignId('created_by')->constrained('users');

            // Supervisor approval
            $table->foreignId('supervisor_id')->nullable()->constrained('users');
            $table->timestamp('supervisor_approved_at')->nullable();
            $table->text('supervisor_notes')->nullable();

            // Manager approval
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->timestamp('manager_approved_at')->nullable();
            $table->text('manager_notes')->nullable();

            // Rejection
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->unique('po_id'); // one comparison per RFQ
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_comparisons');
    }
};
