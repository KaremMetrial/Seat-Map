<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('venue_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#3498db');
            $table->integer('priority')->default(0);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('service_fee', 10, 2)->default(0);
            $table->integer('capacity')->default(0); // 0 = unlimited
            $table->integer('max_booking_per_order')->default(10);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('template_id');
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_zones');
    }
};
