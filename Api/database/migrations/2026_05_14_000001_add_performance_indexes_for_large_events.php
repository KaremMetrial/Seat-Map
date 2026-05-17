<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Event elements: viewport queries (x, y range)
        Schema::table('event_elements', function (Blueprint $table) {
            $table->index(['event_id', 'x', 'y'], 'idx_event_elements_viewport');
            $table->index(['event_id', 'is_bookable'], 'idx_event_elements_bookable');
            $table->index(['event_id', 'zone_id'], 'idx_event_elements_zone');
        });

        // Booking items: seat status lookups
        Schema::table('booking_items', function (Blueprint $table) {
            $table->index(['event_element_id', 'status'], 'idx_booking_items_seat_status');
            $table->index(['event_id', 'status'], 'idx_booking_items_event_status');
        });

        // Element locks: active lock lookups
        Schema::table('element_locks', function (Blueprint $table) {
            $table->index(['event_element_id', 'expires_at'], 'idx_element_locks_active');
            $table->index(['event_id', 'expires_at'], 'idx_element_locks_event_expiry');
            $table->index('lock_key', 'idx_element_locks_key');
        });

        // Bookings: event lookups
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['event_id', 'status'], 'idx_bookings_event_status');
        });
    }

    public function down(): void
    {
        Schema::table('event_elements', function (Blueprint $table) {
            $table->dropIndex('idx_event_elements_viewport');
            $table->dropIndex('idx_event_elements_bookable');
            $table->dropIndex('idx_event_elements_zone');
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropIndex('idx_booking_items_seat_status');
            $table->dropIndex('idx_booking_items_event_status');
        });

        Schema::table('element_locks', function (Blueprint $table) {
            $table->dropIndex('idx_element_locks_active');
            $table->dropIndex('idx_element_locks_event_expiry');
            $table->dropIndex('idx_element_locks_key');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_event_status');
        });
    }
};