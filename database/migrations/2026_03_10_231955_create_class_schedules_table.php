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
    Schema::create('class_schedules', function (Blueprint $table) {
        $table->id();

        $table->foreignId('teacher_id')->constrained('users');
        $table->foreignId('subject_id')->constrained('subjects');
        $table->foreignId('section_id')->constrained('sections');

        $table->string('day'); // lunes, martes, etc
        $table->time('start_time');
        $table->time('end_time');

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
