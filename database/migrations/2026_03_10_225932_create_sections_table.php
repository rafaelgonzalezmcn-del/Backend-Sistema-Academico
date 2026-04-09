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
    Schema::create('sections', function (Blueprint $table) {
        $table->id();
        $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
        $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
        $table->string('name'); // A, B, C
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
