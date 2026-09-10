<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *      title="Sistema Académico API",
 *      description="API REST del Sistema de Gestión Académica - Endpoints para administración, profesores y estudiantes",
 *      version="1.0.0",
 *      contact={
 *          "name": "Desarrollo",
 *          "email": "admin@academico.local"
 *      },
 *      license={
 *          "name": "MIT",
 *          "url": "https://opensource.org/licenses/MIT"
 *      }
 * )
 * 
 * @OA\Server(
 *      url="http://localhost:8000",
 *      description="Servidor de Desarrollo"
 * )
 *
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      description="Ingrese el token JWT obtenido del endpoint de login"
 * )
 */
class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    /**
     * @OA\Post(
     *      path="/api/login",
     *      tags={"Autenticación"},
     *      summary="Iniciar sesión en el sistema",
     *      description="Autentica un usuario y retorna un token de acceso",
     *      operationId="login",
     *      @OA\RequestBody(
     *          required=true,
     *          description="Credenciales de acceso",
     *          @OA\JsonContent(
     *              required={"email", "password"},
     *              @OA\Property(property="email", type="string", format="email", example="admin@admin.com", description="Correo electrónico del usuario"),
     *              @OA\Property(property="password", type="string", example="admin123", description="Contraseña del usuario")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Login exitoso",
     *          @OA\JsonContent(
     *              @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc..."),
     *              @OA\Property(property="token_type", type="string", example="Bearer"),
     *              @OA\Property(property="user", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="first_name", type="string", example="Administrador"),
     *                  @OA\Property(property="last_name", type="string", example="Principal"),
     *                  @OA\Property(property="email", type="string", example="admin@admin.com"),
     *                  @OA\Property(property="role", type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="admin")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Credenciales inválidas",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Las credenciales no son válidas")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Error de validación",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="El campo email es requerido."),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=429,
     *          description="Demasiados intentos",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Demasiados intentos. Intente nuevamente en un minuto.")
     *          )
     *      )
     * )
     */
    public function login(LoginRequest $request)
    {
        try {
            $result = $this->authService->login($request->validated());
            return response()->json($result);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            if (!is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
                $statusCode = 500;
            }
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/logout",
     *      tags={"Autenticación"},
     *      summary="Cerrar sesión",
     *      description="Invalida el token de acceso del usuario actual",
     *      operationId="logout",
     *      security={{"bearerAuth": {}}},
     *      @OA\Response(
     *          response=200,
     *          description="Logout exitoso",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Logout exitoso")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="No autenticado",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      )
     * )
     */
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logout exitoso'
        ]);
    }
}
