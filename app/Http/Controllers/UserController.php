<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\AssignSectionRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *      name="Usuarios",
 *      description="Endpoints de gestión de usuarios (solo Admin)"
 * )
 */
class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * @OA\Get(
     *      path="/api/users",
     *      tags={"Usuarios"},
     *      summary="Listar usuarios",
     *      description="Retorna lista paginada de usuarios. Solo accesible para admin.",
     *      operationId="indexUsers",
     *      security={{"bearerAuth": {}}},
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Número de página",
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Usuarios por página",
     *          @OA\Schema(type="integer", default=15)
     *      ),
     *      @OA\Parameter(
     *          name="role",
     *          in="query",
     *          description="Filtrar por rol (admin, profesor, estudiante)",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="count_only",
     *          in="query",
     *          description="Retornar solo conteo",
     *          @OA\Schema(type="boolean", default=false)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Lista de usuarios",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", type="array", @OA\Items(
     *                  @OA\Property(property="id", type="integer"),
     *                  @OA\Property(property="first_name", type="string"),
     *                  @OA\Property(property="last_name", type="string"),
     *                  @OA\Property(property="email", type="string"),
     *                  @OA\Property(property="role", type="object"),
     *                  @OA\Property(property="activo", type="boolean")
     *              )),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="current_page", type="integer"),
     *                  @OA\Property(property="total", type="integer"),
     *                  @OA\Property(property="per_page", type="integer")
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="No autenticado"),
     *      @OA\Response(response=403, description="Sin permisos")
     * )
     */
    public function index(Request $request)
    {
        // Para count_only, permitir cualquier usuario autenticado
        if ($request->has('count_only')) {
            $query = User::query();
            
            // Aplicar filtros
            if ($request->has('role')) {
                $query->whereHas('role', fn($q) => 
                    $q->where('name', $request->role)
                );
            }
            if ($request->has('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }
            if ($request->has('school_year_id')) {
                $query->whereHas('section', fn($q) =>
                    $q->where('school_year_id', $request->school_year_id)
                );
            }
            if ($request->has('section_id')) {
                $query->where('section_id', $request->section_id);
            }
            
            return $this->success(['total' => $query->count()]);
        }

        // Para lista completa, requerir autorización
        $this->authorize('viewAny', User::class);
        
        $query = User::with(['role', 'section.grade', 'section.schoolYear']);

        // Filtrar por rol
        if ($request->has('role')) {
            $query->whereHas('role', fn($q) => 
                $q->where('name', $request->role)
            );
        }

        // Filtrar por estado activo
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        // Filtrar por año lectivo
        if ($request->has('school_year_id')) {
            $query->whereHas('section', fn($q) =>
                $q->where('school_year_id', $request->school_year_id)
            );
        }

        // Filtrar por sección
        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        // Filtrar por grado (vía sección)
        if ($request->has('grade_id')) {
            $query->whereHas('section', fn($q) =>
                $q->where('grade_id', $request->grade_id)
            );
        }

        // Búsqueda por nombre, email o cédula
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                  ->orWhere('last_name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%")
                  ->orWhere('identification_number', 'ILIKE', "%{$search}%");
            });
        }

        // Filtrar estudiantes sin sección asignada
        if ($request->has('unassigned') && $request->boolean('unassigned')) {
            $query->whereHas('role', fn($q) => $q->where('name', 'estudiante'))
                  ->whereNull('section_id');
        }

        // Filtrar estudiantes elegibles para promoción
        if ($request->has('eligible') && $request->boolean('eligible')) {
            $activeYear = \App\Models\SchoolYear::where('active', true)->first();
            if ($activeYear) {
                $query->whereHas('studentCourses', function ($q) use ($activeYear) {
                    $q->where('school_year_id', $activeYear->id)
                      ->whereIn('status', ['aprobado', 'concluido']);
                })
                ->whereDoesntHave('studentCourses', function ($q) use ($activeYear) {
                    $q->where('school_year_id', $activeYear->id)
                      ->where('status', 'reprobado');
                });
            }
        }

        // Usar paginación por defecto (15 por página)
        // Solo obtener todos SIN paginación cuando se especifica per_page=-1
        $perPage = $request->query('per_page', 15);
        if ($perPage == -1) {
            $users = $query->get();
            // Envolver en paginator sintético para compatibilidad
            $users = new \Illuminate\Pagination\LengthAwarePaginator(
                $users,
                $users->count(),
                $users->count(),
                1
            );
        } else {
            $users = $query->paginate($perPage);
        }
        
        return response()->json([
            'data' => UserResource::collection($users)->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
            ],
        ]);
    }

    /**
     * GET /users/count
     * Contar usuarios (solo admin)
     */
    public function count(Request $request)
    {
        $this->authorize('viewAny', User::class);
        
        $query = User::query();

        // Aplicar los mismos filtros que en index
        if ($request->has('role')) {
            $query->whereHas('role', fn($q) => 
                $q->where('name', $request->role)
            );
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('school_year_id')) {
            $query->whereHas('section', fn($q) =>
                $q->where('school_year_id', $request->school_year_id)
            );
        }

        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        return $this->success(['total' => $query->count()]);
    }

    /**
     * GET /users/check-identification
     * Verificar si una cédula ya existe (validación en tiempo real).
     * Normaliza la cédula (solo dígitos) antes de consultar.
     * Solo accesible para admin.
     */
    public function checkIdentification(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'number' => 'required|string|max:255',
            'exclude_user_id' => 'nullable|integer|exists:users,id',
        ]);

        // F9-EX1: Normalizar — solo dígitos
        $number = preg_replace('/\D/', '', $request->number);

        if (empty($number)) {
            return response()->json(['message' => 'El número de identificación no contiene dígitos válidos'], 422);
        }

        // F9-EX3: Si viene exclude_user_id y la cédula pertenece a ese usuario, está "disponible"
        if ($request->has('exclude_user_id')) {
            $currentUser = User::find($request->exclude_user_id);
            if ($currentUser && preg_replace('/\D/', '', $currentUser->identification_number ?? '') === $number) {
                return $this->success(['available' => true, 'user' => null]);
            }
        }

        $user = User::where('identification_number', $number)->first();

        if ($user) {
            return $this->success([
                'available' => false,
                'user' => [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'role' => $user->role?->name,
                ],
            ]);
        }

        return $this->success(['available' => true, 'user' => null]);
    }

    /**
     * GET /users/check-email
     * Verificar si un email ya existe (validación en tiempo real).
     * Normaliza el email (lowercase, trim) antes de consultar.
     * Solo accesible para admin.
     */
    public function checkEmail(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'email' => 'required|email|max:255',
            'exclude_user_id' => 'nullable|integer|exists:users,id',
        ]);

        // F9-EX7: Normalizar email
        $email = strtolower(trim($request->email));

        // F9-EX3: Si viene exclude_user_id y el email pertenece a ese usuario, está "disponible"
        if ($request->has('exclude_user_id')) {
            $currentUser = User::find($request->exclude_user_id);
            if ($currentUser && strtolower(trim($currentUser->email ?? '')) === $email) {
                return $this->success(['available' => true, 'user' => null]);
            }
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            return $this->success([
                'available' => false,
                'user' => [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'role' => $user->role?->name,
                ],
            ]);
        }

        return $this->success(['available' => true, 'user' => null]);
    }

    /**
     * GET /users/check-availability
     * Endpoint unificado de validación en tiempo real (F9-H2).
     * Acepta field=email o field=identification_number.
     * Mantiene compatibilidad con los endpoints individuales.
     */
    public function checkAvailability(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $request->validate([
            'field' => 'required|in:email,identification_number',
            'value' => 'required|string|max:255',
            'exclude_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $field = $request->field;
        $value = $request->value;

        // Normalizar según campo
        if ($field === 'email') {
            $value = strtolower(trim($value));
        } elseif ($field === 'identification_number') {
            $value = preg_replace('/\D/', '', $value);
            if (empty($value)) {
                return response()->json(['message' => 'El valor no contiene dígitos válidos'], 422);
            }
        }

        // Protección edición: si el valor pertenece al usuario actual, está "disponible"
        if ($request->has('exclude_user_id')) {
            $currentUser = User::find($request->exclude_user_id);
            if ($currentUser) {
                $currentValue = $field === 'email'
                    ? strtolower(trim($currentUser->email ?? ''))
                    : preg_replace('/\D/', '', $currentUser->identification_number ?? '');
                if ($currentValue === $value) {
                    return $this->success(['available' => true, 'user' => null]);
                }
            }
        }

        $user = User::where($field, $value)->first();

        if ($user) {
            return $this->success([
                'available' => false,
                'user' => [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'role' => $user->role?->name,
                ],
            ]);
        }

        return $this->success(['available' => true, 'user' => null]);
    }

    /**
     * POST /users
     * Crear usuario
     */
    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);
        
        $user = $this->userService->create($request->validated());

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Usuario creado correctamente'
        ], 201);
    }

    /**
     * GET /users/{user}
     * Ver usuario
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);
        
        return response()->json([
            'data' => new UserResource($user->load(['role', 'section']))
        ]);
    }

    /**
     * PUT /users/{user}
     * Actualizar usuario
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);
        
        $user = $this->userService->update($user, $request->validated());

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Usuario actualizado correctamente'
        ]);
    }

    /**
     * DELETE /users/{user}
     * Desactivar usuario
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        
        $this->userService->deactivate($user);

        return response()->json(['message' => 'Usuario desactivado correctamente']);
    }

    /**
     * PATCH /users/{user}/assign-section
     * Asignar sección a estudiante
     */
    public function assignSection(AssignSectionRequest $request, User $user)
    {
        $this->authorize('assignSection', $user);
        
        try {
            $user = $this->userService->assignSection(
                $user,
                $request->input('section_id'),
                $request->boolean('force')
            );

            return response()->json([
                'data' => new UserResource($user),
                'message' => 'Sección asignada correctamente'
            ]);

        } catch (\Exception $e) {
            // Verificar si es la excepción especial con datos de la sección actual
            $message = $e->getMessage();
            $data = @json_decode($message, true);
            
            if ($data && isset($data['current_section'])) {
                // Contexto del conflicto en "data" (formato de error estándar)
                return $this->error($data['message'], 409, [], [
                    'current_section' => $data['current_section'],
                ]);
            }
            
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * POST /admin/users/batch-preview
     * Vista previa de operación masiva antes de ejecutar.
     * F12-H3: Permite al admin ver qué pasará antes de confirmar.
     * F12-H9: Optimizado — usa whereIn en vez de loop.
     */
    public function batchPreview(Request $request)
    {
        $this->authorize('viewAny', User::class);

        // F12-H4: Límite de seguridad — ANTES de validación
        if ($request->has('user_ids') && is_array($request->user_ids) && count($request->user_ids) > 200) {
            return response()->json([
                'message' => 'Máximo 200 usuarios por operación masiva',
            ], 422);
        }

        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'action' => 'required|in:assign_section,deactivate',
            'section_id' => 'nullable|integer|exists:sections,id',
        ]);

        // F12-H4: Límite de seguridad
        $userIds = array_unique($request->user_ids);
        if (count($userIds) > 200) {
            return response()->json([
                'message' => 'Máximo 200 usuarios por operación masiva',
            ], 422);
        }

        // F12-H9: Optimización — cargar todos en una sola query
        $users = User::whereIn('id', $userIds)
            ->with(['role', 'section'])
            ->get()
            ->keyBy('id');

        $willUpdate = 0;
        $willFail = 0;
        $details = [];

        foreach ($userIds as $userId) {
            $user = $users->get($userId);
            if (!$user) {
                $willFail++;
                $details[] = ['user_id' => $userId, 'status' => 'error', 'reason' => 'Usuario no encontrado'];
                continue;
            }

            $userName = trim($user->first_name . ' ' . $user->last_name);

            if ($request->action === 'assign_section') {
                if (!$user->isStudent()) {
                    $willFail++;
                    $details[] = ['user_id' => $userId, 'user_name' => $userName, 'status' => 'error', 'reason' => 'No es estudiante'];
                } elseif ($user->section_id && !$request->boolean('force', false)) {
                    $willFail++;
                    $details[] = ['user_id' => $userId, 'user_name' => $userName, 'status' => 'conflict', 'reason' => 'Ya tiene sección asignada'];
                } else {
                    $willUpdate++;
                    $details[] = ['user_id' => $userId, 'user_name' => $userName, 'status' => 'ok'];
                }
            } elseif ($request->action === 'deactivate') {
                if ($userId == request()->user()?->id) {
                    $willFail++;
                    $details[] = ['user_id' => $userId, 'user_name' => 'Tú (admin actual)', 'status' => 'error', 'reason' => 'No puedes desactivarte a ti mismo'];
                } elseif (!$user->activo) {
                    $willFail++;
                    $details[] = ['user_id' => $userId, 'user_name' => $userName, 'status' => 'conflict', 'reason' => 'Ya está desactivado'];
                } else {
                    $willUpdate++;
                    $details[] = ['user_id' => $userId, 'user_name' => $userName, 'status' => 'ok'];
                }
            }
        }

        return response()->json([
            'data' => [
                'will_update' => $willUpdate,
                'will_fail' => $willFail,
                'total' => count($userIds),
                'details' => $details,
            ],
        ]);
    }

    /**
     * PATCH /admin/users/batch-assign-section
     * Asignar sección a múltiples estudiantes en lote.
     * Solo accesible para admin.
     * F12-T2: Soft-fail — procesa todos, reporta errores individuales.
     */
    public function batchAssignSection(Request $request)
    {
        $this->authorize('viewAny', User::class);

        // F12-H4: Límite de seguridad — ANTES de validación
        if ($request->has('user_ids') && is_array($request->user_ids) && count($request->user_ids) > 200) {
            return response()->json([
                'message' => 'Máximo 200 usuarios por operación masiva',
            ], 422);
        }

        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'section_id' => 'required|integer|exists:sections,id',
            'force' => 'boolean',
        ]);

        // F12-EX3: Evitar duplicados
        $userIds = array_unique($request->user_ids);

        // F12-H4: Límite de seguridad
        if (count($userIds) > 200) {
            return response()->json([
                'message' => 'Máximo 200 usuarios por operación masiva',
            ], 422);
        }

        $sectionId = $request->section_id;
        $force = $request->boolean('force', false);

        $success = 0;
        $errors = [];

        // F12-H9: Optimización — cargar todos en una sola query
        $users = User::whereIn('id', $userIds)->with('role')->get()->keyBy('id');

        foreach ($userIds as $userId) {
            try {
                $user = $users->get($userId);

                // Validar que es estudiante
                if (!$user->isStudent()) {
                    $errors[] = [
                        'user_id' => $userId,
                        'user_name' => trim($user->first_name . ' ' . $user->last_name),
                        'reason' => 'El usuario no es un estudiante',
                    ];
                    continue;
                }

                // Si ya tiene sección y no es force
                if ($user->section_id && !$force) {
                    $errors[] = [
                        'user_id' => $userId,
                        'user_name' => trim($user->first_name . ' ' . $user->last_name),
                        'reason' => 'Ya tiene sección asignada',
                    ];
                    continue;
                }

                $user->update(['section_id' => $sectionId]);
                $success++;

            } catch (\Exception $e) {
                $user = User::find($userId);
                $errors[] = [
                    'user_id' => $userId,
                    'user_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'Desconocido',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        // F12-EX6: Logging masivo
        Log::info('Batch operation: assign_section', [
            'total_requested' => count($userIds),
            'success' => $success,
            'errors' => count($errors),
            'section_id' => $sectionId,
            'force' => $force,
            'performed_by' => request()->user()?->id,
        ]);

        return response()->json([
            'data' => [
                'success' => $success,
                'errors' => $errors,
                'total' => count($userIds),
            ],
            'message' => "{$success} usuario(s) actualizados, " . count($errors) . " error(es)",
        ]);
    }

    /**
     * PATCH /admin/users/batch-deactivate
     * Desactivar múltiples usuarios en lote.
     * Solo accesible para admin.
     * F12-T3: Soft-fail + protección del admin actual.
     */
    public function batchDeactivate(Request $request)
    {
        $this->authorize('viewAny', User::class);

        // F12-H4: Límite de seguridad — ANTES de validación
        if ($request->has('user_ids') && is_array($request->user_ids) && count($request->user_ids) > 200) {
            return response()->json([
                'message' => 'Máximo 200 usuarios por operación masiva',
            ], 422);
        }

        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        // F12-EX3: Evitar duplicados
        $userIds = array_unique($request->user_ids);

        // F12-H4: Límite de seguridad
        if (count($userIds) > 200) {
            return response()->json([
                'message' => 'Máximo 200 usuarios por operación masiva',
            ], 422);
        }

        $currentUserId = request()->user()?->id;

        $success = 0;
        $errors = [];

        // F12-H9: Optimización — cargar todos en una sola query
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($userIds as $userId) {
            try {
                // F12-EX3: No desactivar al admin actual
                if ($userId == $currentUserId) {
                    $errors[] = [
                        'user_id' => $userId,
                        'user_name' => 'Tú (admin actual)',
                        'reason' => 'No puedes desactivarte a ti mismo',
                    ];
                    continue;
                }

                $user = $users->get($userId);
                if (!$user) {
                    $errors[] = ['user_id' => $userId, 'user_name' => 'Desconocido', 'reason' => 'Usuario no encontrado'];
                    continue;
                }

                if (!$user->activo) {
                    $errors[] = [
                        'user_id' => $userId,
                        'user_name' => trim($user->first_name . ' ' . $user->last_name),
                        'reason' => 'Ya está desactivado',
                    ];
                    continue;
                }

                $user->update(['activo' => false]);
                $success++;

            } catch (\Exception $e) {
                $user = $users->get($userId);
                $errors[] = [
                    'user_id' => $userId,
                    'user_name' => $user ? trim($user->first_name . ' ' . $user->last_name) : 'Desconocido',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        // F12-EX6: Logging masivo
        Log::info('Batch operation: deactivate', [
            'total_requested' => count($userIds),
            'success' => $success,
            'errors' => count($errors),
            'performed_by' => request()->user()?->id,
        ]);

        return response()->json([
            'data' => [
                'success' => $success,
                'errors' => $errors,
                'total' => count($userIds),
            ],
            'message' => "{$success} usuario(s) desactivados, " . count($errors) . " error(es)",
        ]);
    }

    /**
     * PUT /profile
     * Actualizar perfil del usuario autenticado
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();

        // Verificar contraseña actual si se va a cambiar
        if ($request->wantsToChangePassword()) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'La contraseña actual es incorrecta'
                ], 422);
            }
        }

        $user = $this->userService->updateProfile($user, $request->validated());

        return $this->success(new UserResource($user), 'Perfil actualizado correctamente');
    }

    /**
     * POST /profile/selfie
     * Subir selfie (foto de perfil almacenada en BD)
     */
    public function uploadSelfie(Request $request)
    {
        $user = $request->user();
        $maxBytes = 2 * 1024 * 1024; // 2 MB

        // Se acepta base64 (JSON) o archivo multipart
        if ($request->filled('selfie_data')) {
            $base64 = (string) $request->input('selfie_data');
            // Permitir prefijo "data:image/png;base64,"
            if (str_contains($base64, ',')) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            if (strlen($base64) > (int) ceil($maxBytes * 4 / 3) + 4) {
                return response()->json(['message' => 'La imagen no puede superar 2 MB'], 422);
            }
            $imageData = base64_decode($base64, true); // modo estricto
            if ($imageData === false || $imageData === '') {
                return response()->json(['message' => 'Datos base64 inválidos'], 422);
            }
        } elseif ($request->hasFile('selfie')) {
            $request->validate([
                'selfie' => 'required|image|mimes:jpeg,png|max:2048'
            ]);
            $imageData = file_get_contents($request->file('selfie')->getRealPath());
        } else {
            return response()->json(['message' => 'No se recibió ninguna imagen'], 422);
        }

        if (strlen($imageData) > $maxBytes) {
            return response()->json(['message' => 'La imagen no puede superar 2 MB'], 422);
        }

        // El tipo se detecta a partir del contenido real del archivo.
        // Nunca se usa el "mime_type" que envía el cliente: así nadie puede
        // guardar HTML/JS haciéndolo pasar por imagen (XSS almacenado).
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($imageData);
        if (!in_array($mimeType, self::SELFIE_MIMES, true) || @getimagesizefromstring($imageData) === false) {
            return response()->json(['message' => 'La selfie debe ser una imagen JPEG o PNG válida'], 422);
        }

        try {
            // PostgreSQL bytea: se usa PDO directamente para enviar datos binarios
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $stmt = $pdo->prepare('UPDATE users SET selfie = :selfie::bytea, selfie_mime = :mime WHERE id = :id');
            $stmt->bindValue(':selfie', $imageData, \PDO::PARAM_LOB);
            $stmt->bindValue(':mime', $mimeType);
            $stmt->bindValue(':id', $user->id, \PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Exception $e) {
            Log::error('uploadSelfie - Error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al guardar la selfie'], 500);
        }

        return response()->json([
            'message' => 'Selfie actualizada correctamente'
        ]);
    }

    /** Tipos de imagen permitidos para la selfie */
    private const SELFIE_MIMES = ['image/jpeg', 'image/png'];

    /**
     * Respuesta segura para devolver una selfie guardada en BD.
     */
    private function selfieResponse(User $user)
    {
        if (!$user->selfie) {
            return response()->json(['message' => 'No hay selfie disponible'], 404);
        }

        // Los datos bytea de PostgreSQL pueden venir como resource
        $selfieData = $user->selfie;
        if (is_resource($selfieData)) {
            $selfieData = stream_get_contents($selfieData);
        }

        // Por si quedaron registros antiguos con un MIME no permitido
        $mime = in_array($user->selfie_mime, self::SELFIE_MIMES, true) ? $user->selfie_mime : 'image/jpeg';

        return response($selfieData)
            ->header('Content-Type', $mime)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'");
    }

    /**
     * GET /users/{user}/selfie
     * Obtener la selfie de cualquier usuario (para display en listas)
     * FASE 2: Verificación de acceso - solo usuarios con relación académica legítima
     */
    public function getUserSelfie(Request $request, User $user)
    {
        $authUser = $request->user();
        
        // FASE 2: Verificar acceso antes de permitir ver la selfie
        // Admin tiene acceso total
        if ($authUser->isAdmin()) {
            $hasAccess = true;
        }
        // Mismo usuario puede ver su propia selfie
        elseif ($authUser->id === $user->id) {
            $hasAccess = true;
        }
        // Profesor puede ver selfie de estudiantes de sus secciones
        elseif ($authUser->isTeacher() && $user->section_id) {
            $hasAccess = \App\Models\ClassSchedule::where('teacher_id', $authUser->id)
                ->where('section_id', $user->section_id)
                ->exists();
        }
        // Estudiante puede ver selfie de compañeros de su sección
        elseif ($authUser->isStudent() && $user->section_id) {
            $hasAccess = $authUser->section_id === $user->section_id;
        }
        else {
            $hasAccess = false;
        }
        
        if (!$hasAccess) {
            return response()->json([
                'message' => 'No tienes acceso a la selfie de este usuario'
            ], 403);
        }
        
        return $this->selfieResponse($user);
    }

    /**
     * GET /profile/selfie
     * Obtener la selfie del usuario autenticado
     */
    public function getSelfie(Request $request)
    {
        return $this->selfieResponse($request->user());
    }

    /**
     * DELETE /profile/selfie
     * Eliminar la selfie del usuario autenticado
     */
    public function deleteSelfie(Request $request)
    {
        $user = $request->user();
        
        // Eliminar la selfie estableciendo los campos a null
        try {
            $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $stmt = $pdo->prepare('UPDATE users SET selfie = NULL, selfie_mime = NULL WHERE id = ?');
            $stmt->execute([$user->id]);
            
            Log::info('deleteSelfie - Selfie deleted for user: ' . $user->id);
            
            return response()->json([
                'message' => 'Selfie eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('deleteSelfie - Error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al eliminar la selfie'], 500);
        }
    }
}
