<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference', 50)->unique();  // Public reference e.g. BK-ABCD1234
            $table->string('internal_reference', 100)->nullable(); // lock_key for idempotency
            $table->foreignId('event_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Customer info (stored even for guest checkouts)
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 50)->nullable();

            // Financial
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // Status flow: pending → locked → confirmed → completed / cancelled / expired
            $table->enum('status', [
                'pending',
                'locked',
                'confirmed',
                'completed',
                'cancelled',
                'expired',
            ])->default('pending');

            // Lifecycle timestamps
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Lock expiration

            // Payment
            $table->string('payment_intent_id')->nullable();
            $table->string('payment_provider', 50)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('booking_reference');
            $table->index('internal_reference');
            $table->index(['event_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
