<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('venue_templates')->cascadeOnDelete();
            $table->string('element_type'); // seat, section, table, stage, shape, entrance, text
            $table->decimal('x', 10, 2);
            $table->decimal('y', 10, 2);
            $table->decimal('width', 10, 2)->default(0);
            $table->decimal('height', 10, 2)->default(0);
            $table->decimal('rotation', 5, 2)->default(0);
            $table->integer('z_index')->default(0);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->json('data_json')->nullable();  // label, row, capacity, curve params…
            $table->json('style_json')->nullable();  // fill, stroke, opacity…
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id')
                ->on('template_elements')
                ->nullOnDelete();

            $table->index('template_id');
            $table->index('element_type');
            $table->index('parent_id');
            $table->index('z_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_elements');
    }
};
