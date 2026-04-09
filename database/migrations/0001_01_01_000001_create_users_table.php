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
    Schema::create('users', function (Blueprint $table) {
        $table->id();

        // Datos personales
        $table->string('first_name');
        $table->string('last_name')->nullable();
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();

        // Autenticación
        $table->string('password');
        $table->rememberToken();

        // Identificación académica
        $table->string('identification_number')->nullable()->unique();
        $table->string('phone')->nullable();

        // Relación con roles
        $table->foreignId('role_id')
              ->constrained('roles')
              ->cascadeOnUpdate()
              ->restrictOnDelete();

        // Estado del usuario (lo que pediste)
        $table->boolean('activo')->default(true);

        // Control
        $table->timestamp('last_login_at')->nullable();

        $table->softDeletes(); // borrado lógico
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
