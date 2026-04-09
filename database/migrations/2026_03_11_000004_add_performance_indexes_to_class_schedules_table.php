<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->index(['teacher_id', 'day', 'start_time'], 'idx_teacher_day_start');
            $table->index(['section_id', 'day', 'start_time'], 'idx_section_day_start');
            $table->index(['school_year_id', 'day'], 'idx_school_year_day');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_teacher_day_start');
            $table->dropIndex('idx_section_day_start');
            $table->dropIndex('idx_school_year_day');
        });
    }
};
