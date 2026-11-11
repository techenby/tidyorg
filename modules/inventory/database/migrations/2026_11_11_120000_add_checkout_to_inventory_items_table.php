<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The person is an id in whichever module provides the people directory, so
     * there is deliberately no foreign key: Inventory does not know that table.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedBigInteger('checked_out_to')->nullable();
            $table->timestamp('checked_out_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['checked_out_to', 'checked_out_at']);
        });
    }
};
