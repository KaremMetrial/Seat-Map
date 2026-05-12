<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds unique constraint to prevent double-locking of the same element.
     * This is the database-level enforcement layer for seat locking.
     */
    public function up(): void
    {
        Schema::table('element_locks', function (Blueprint $table) {
            // Unique constraint ensures only one active lock per element
            // This catches race conditions that escape the application-level checks
            $table->unique('event_element_id', 'element_locks_event_element_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('element_locks', function (Blueprint $table) {
            $table->dropUnique('element_locks_event_element_id_unique');
        });
    }
};
