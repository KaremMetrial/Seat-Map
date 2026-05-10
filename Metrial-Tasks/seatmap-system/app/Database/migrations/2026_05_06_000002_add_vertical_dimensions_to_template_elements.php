<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_elements', function (Blueprint $table) {
            $table->decimal('z', 10, 2)->nullable()->after('y');
            $table->decimal('vertical_clearance', 10, 2)->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('template_elements', function (Blueprint $table) {
            $table->dropColumn(['z', 'vertical_clearance']);
        });
    }
};
