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
        Schema::table('users', function (Blueprint $table) {
            // Columnas para selfie (foto de perfil almacenada en BD)
            $table->binary('selfie')->nullable()->comment('Foto de perfil en formato binario');
            $table->string('selfie_mime')->nullable()->comment('Tipo MIME de la imagen (image/jpeg, image/png, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['selfie', 'selfie_mime']);
        });
    }
};