<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('venue_templates');
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('booking_open_at')->nullable();
            $table->dateTime('booking_close_at')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled', 'postponed'])
                ->default('draft');
            $table->timestamp('snapshotted_at')->nullable();
            $table->integer('snapshot_version')->default(1);
            $table->integer('total_capacity')->default(0);
            $table->integer('available_capacity')->default(0);
            $table->integer('sold_count')->default(0);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('template_id');
            $table->index('status');
            $table->index('start_at');
            $table->index(['status', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
