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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // admin, estudiante, profesor
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insertar roles por defecto
        DB::table('roles')->insert([
            [
                'name' => 'admin',
                'description' => 'Administrador del sistema',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'estudiante',
                'description' => 'Usuario estudiante',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'profesor',
                'description' => 'Usuario profesor',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};