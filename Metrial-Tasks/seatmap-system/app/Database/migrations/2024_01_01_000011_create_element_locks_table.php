<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temporary locks on elements during the booking process.
     * Prevents race conditions and double booking.
     *
     * UNIQUE CONSTRAINT DESIGN:
     * The unique key is on event_element_id alone — one row per element.
     * Expired rows are deleted (not updated) before a new lock is inserted.
     * SeatLockService handles this cleanup atomically inside a transaction.
     *
     * WHY NOT UNIQUE (event_element_id, expires_at)?
     * expires_at changes on every insert, so the composite key would allow
     * multiple active locks on the same element — defeating its purpose.
     *
     * WHY lock_key NOT NULL?
     * A nullable lock_key would cause all callers that omit it to share the
     * same Redis mutex key ("seatlock:") — a silent collision.
     */
    public function up(): void
    {
        Schema::create('element_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_element_id');
            $table->foreignId('event_id')->constrained();
            $table->string('lock_key');                    // NOT NULL — always required
            $table->string('booking_reference')->nullable(); // Linked after booking created
            $table->timestamp('expires_at');
            $table->timestamp('locked_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // One active lock row per element — the core double-booking guard
            $table->unique('event_element_id', 'unique_element_lock');

            $table->index('event_id');
            $table->index('lock_key');
            $table->index('expires_at');
            $table->index(['event_element_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('element_locks');
    }
};
