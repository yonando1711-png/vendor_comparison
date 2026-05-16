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
        // MySQL requires raw ALTER to change ENUM values
        \DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('creator','supervisor','manager','admin') NOT NULL DEFAULT 'creator'");
    }

    public function down(): void
    {
        \DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('creator','supervisor','manager') NOT NULL DEFAULT 'creator'");
    }
};
