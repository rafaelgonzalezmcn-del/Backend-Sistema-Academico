<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

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

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logout exitoso'
        ]);
    }
}
