<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SNAPSHOT TABLE — CRITICAL FOR DATA INTEGRITY
     *
     * This table holds a point-in-time copy of template_elements.
     * Once created at publish time, it NEVER changes.
     *
     * WHY: If we used template_elements directly:
     *   - Admin could modify layout while users are booking
     *   - Pricing could change mid-booking
     *   - Seats could disappear after selection
     *
     * NOTE ON booking_status:
     * There is NO booking_status / computed_status column here.
     * A plain ENUM column with a static default is never automatically
     * updated — it would always read 'available' unless the application
     * manually updated it on every lock/unlock/book/cancel, which creates
     * a sync problem worse than the one it solves.
     *
     * Status is derived at query time via two N+1-safe patterns in
     * EventElement — see EventElement::withBookingStatus() and
     * EventElement::hydrateBookingStatus().
     */
    public function up(): void
    {
        Schema::create('event_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Reference to original template element (audit trail only)
            $table->unsignedBigInteger('template_element_id')->nullable();

            // SNAPSHOT DATA — copied at publish time, NEVER changes
            $table->string('element_type');
            $table->decimal('x', 10, 2);
            $table->decimal('y', 10, 2);
            $table->decimal('width', 10, 2)->default(0);
            $table->decimal('height', 10, 2)->default(0);
            $table->decimal('rotation', 5, 2)->default(0);
            $table->integer('z_index')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->json('data_json')->nullable();   // SNAPSHOTTED — never changes
            $table->json('style_json')->nullable();  // SNAPSHOTTED — never changes

            // Bookable flag — can be disabled per event without touching template
            $table->boolean('is_bookable')->default(true);

            // Zone mapping (snapshotted from element_zone_map at publish time)
            $table->unsignedBigInteger('zone_id')->nullable();

            // Price at time of booking (set when booking_item is created)
            $table->decimal('booked_price', 10, 2)->nullable();

            // Explicit timestamps — EventElement::insert() bypasses Eloquent
            // auto-timestamps, so TemplateElement::toEventElement() sets these.
            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────
            // Primary seatmap fetch: all elements for an event, ordered by z_index
            $table->index('event_id');

            // Capacity count queries (bookable elements per event)
            $table->index(['event_id', 'is_bookable']);

            // Element type filtering (e.g. fetch only seats, not stages)
            $table->index(['event_id', 'element_type']);

            // Parent-child hierarchy traversal
            $table->index('parent_id');

            // Zone-based pricing lookups
            $table->index('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_elements');
    }
};
