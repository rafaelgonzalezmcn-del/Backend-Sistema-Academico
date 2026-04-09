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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('description'); // "Usuario creado", "Usuario modificado", etc.
            $table->string('subject_type'); // Tipo de modelo (App\Models\User, etc.)
            $table->unsignedBigInteger('subject_id'); // ID del registro afectado
            $table->unsignedBigInteger('user_id'); // Usuario que realizó la acción
            $table->text('changes')->nullable(); // JSON con cambios antes/después
            $table->string('ip_address')->nullable(); // IP del usuario
            $table->text('user_agent')->nullable(); // Navegador del usuario
            $table->timestamps();
            
            // Índices
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
