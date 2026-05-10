<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_templates', function (Blueprint $table) {
            $table->decimal('scale_factor', 10, 4)->nullable()->after('is_active');
            $table->string('units', 20)->nullable()->after('scale_factor');
            $table->decimal('origin_offset_x', 10, 2)->nullable()->after('units');
            $table->decimal('origin_offset_y', 10, 2)->nullable()->after('origin_offset_x');
            $table->decimal('rotation_degrees', 5, 2)->nullable()->after('origin_offset_y');
        });
    }

    public function down(): void
    {
        Schema::table('venue_templates', function (Blueprint $table) {
            $table->dropColumn([
                'scale_factor',
                'units',
                'origin_offset_x',
                'origin_offset_y',
                'rotation_degrees',
            ]);
        });
    }
};
