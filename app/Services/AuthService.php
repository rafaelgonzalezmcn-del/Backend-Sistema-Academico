<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Traits\LogsActivity;

// F4-T1: Agregar trait de logging
class AuthService
{
    use LogsActivity;

    /**
     * Autenticar usuario (F4-T1)
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw new \Exception('Usuario no encontrado', 404);
        }

        if (!$user->activo) {
            throw new \Exception('Usuario desactivado', 403);
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            throw new \Exception('Contraseña incorrecta', 401);
        }

        // Actualizar último login
        $user->last_login_at = now();
        $user->save();

        // F4-T1: Log de login exitoso - pasar user_id explícitamente
        $this->logActivity('Usuario inició sesión', $user, null, $user->id);

        // Crear token
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'Login exitoso',
            'token' => $token,
            'user' => $user->load('role')
        ];
    }

    /**
     * Cerrar sesión (F4-T1)
     */
    public function logout($user): void
    {
        if ($user) {
            $userId = $user->id; // Guardar ID antes de eliminar token
            $user->currentAccessToken()->delete();
            
            // F4-T1: Log de logout - pasar user_id explícitamente
            $this->logActivity('Usuario cerró sesión', $user, null, $userId);
        }
    }
}
