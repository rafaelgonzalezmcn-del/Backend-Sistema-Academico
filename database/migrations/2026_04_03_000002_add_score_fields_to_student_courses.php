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
        Schema::table('student_courses', function (Blueprint $table) {
            $table->decimal('total_score_obtained', 10, 2)->nullable()->after('parcial_grades');
            $table->decimal('total_score_possible', 10, 2)->nullable()->after('total_score_obtained');
            $table->decimal('passing_percentage', 5, 2)->default(70.00)->after('total_score_possible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->dropColumn(['total_score_obtained', 'total_score_possible', 'passing_percentage']);
        });
    }
};
