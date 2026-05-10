<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('event_element_id');
            $table->foreignId('event_id')->constrained();
            $table->string('element_type', 50);
            $table->string('label', 50)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->enum('status', ['booked', 'cancelled'])->default('booked');
            $table->timestamps();

            // CRITICAL: prevents double-booking.
            // Only one row with status='booked' can exist per event_element_id.
            // Cancelling sets status='cancelled', which frees the slot.
            $table->unique(['event_element_id', 'status'], 'unique_booked_element');

            $table->index('booking_id');
            $table->index('event_element_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
