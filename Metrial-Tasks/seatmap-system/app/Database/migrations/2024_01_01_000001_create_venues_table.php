<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->enum('venue_type', ['cinema', 'stadium', 'theater', 'custom'])->default('custom');
            $table->integer('default_width')->default(1000);
            $table->integer('default_height')->default(600);
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('venue_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
