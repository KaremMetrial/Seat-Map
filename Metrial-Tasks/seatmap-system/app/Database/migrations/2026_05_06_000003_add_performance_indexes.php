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
        // Add composite index for booking_items to speed up availability checks
        Schema::table('booking_items', function (Blueprint $table) {
            $table->index(['event_element_id', 'status'], 'idx_element_status');
            $table->index(['event_id', 'status'], 'idx_booking_event_status');
        });

        // Add composite index for element_locks
        Schema::table('element_locks', function (Blueprint $table) {
            $table->index(['lock_key', 'expires_at'], 'idx_lockkey_expires');
        });

        // Add composite index for event_elements
        Schema::table('event_elements', function (Blueprint $table) {
            $table->index(['event_id', 'is_bookable', 'element_type'], 'idx_event_bookable_type');
            $table->index(['event_id', 'z_index'], 'idx_event_zindex');
        });

        // Add index for bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'idx_status_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropIndex('idx_element_status');
            $table->dropIndex('idx_booking_event_status');
        });

        Schema::table('element_locks', function (Blueprint $table) {
            $table->dropIndex('idx_lockkey_expires');
        });

        Schema::table('event_elements', function (Blueprint $table) {
            $table->dropIndex('idx_event_bookable_type');
            $table->dropIndex('idx_event_zindex');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_status_expires');
        });
    }
};
