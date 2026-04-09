<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parciales', function (Blueprint $table) {
            $table->integer('nota_maxima')->default(100)->after('modulo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parciales', function (Blueprint $table) {
            $table->dropColumn('nota_maxima');
        });
    }
};
