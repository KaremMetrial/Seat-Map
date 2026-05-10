<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->enum('rule_type', ['early_bird', 'last_minute', 'group', 'zone', 'time_based', 'custom']);
            $table->json('conditions_json')->nullable(); // date_range, min_quantity, etc.
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->enum('adjustment_type', ['fixed', 'percent'])->default('fixed');
            $table->integer('priority')->default(0);
            $table->foreignId('zone_id')->nullable()->constrained('template_zones')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('venue_templates')->nullOnDelete();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('rule_type');
            $table->index('priority');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
