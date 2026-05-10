<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('element_zone_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_element_id')
                ->constrained('template_elements')
                ->cascadeOnDelete();
            $table->foreignId('template_zone_id')
                ->constrained('template_zones')
                ->cascadeOnDelete();
            $table->decimal('price_modifier', 10, 2)->default(0);
            $table->enum('modifier_type', ['fixed', 'percent'])->default('fixed');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['template_element_id', 'template_zone_id'], 'uk_element_zone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('element_zone_map');
    }
};
