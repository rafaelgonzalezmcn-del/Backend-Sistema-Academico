<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Resources\UserResource;
use App\Services\EnrollmentService;
use App\Models\User;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function __construct(
        private EnrollmentService $enrollmentService
    ) {}

    /**
     * POST /admin/enrollments
     * Matricular estudiante (nuevo o existente) en una sección.
     * Operación transaccional.
     */
    public function enroll(Request $request)
    {
        $this->authorize('create', User::class);

        $all = $request->all();

        $isNew = filled($all['first_name'] ?? null);

        if ($isNew) {
            $rules = [
                'first_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|min:8|string',
                'section_id' => 'required|exists:sections,id',
            ];
            $messages = [
                'first_name.required' => 'El nombre del estudiante es requerido',
                'email.required' => 'El correo electrónico es requerido',
                'password.required' => 'La contraseña es requerida',
                'section_id.required' => 'Debe seleccionar una sección',
                'section_id.exists' => 'La sección seleccionada no existe',
            ];
        } else {
            $rules = [
                'student_id' => 'required|exists:users,id',
                'section_id' => 'required|exists:sections,id',
            ];
            $messages = [
                'student_id.required' => 'Debe seleccionar un estudiante',
                'student_id.exists' => 'El estudiante seleccionado no existe',
                'section_id.required' => 'Debe seleccionar una sección',
                'section_id.exists' => 'La sección seleccionada no existe',
            ];
        }

        validator($all, $rules, $messages)->validate();

        try {
            $sectionId = $all['section_id'];

            if ($isNew) {
                $studentData = [
                    'first_name' => $all['first_name'],
                    'last_name' => $all['last_name'] ?? null,
                    'email' => $all['email'],
                    'password' => $all['password'],
                    'identification_number' => $all['identification_number'] ?? null,
                    'phone' => $all['phone'] ?? null,
                ];
                $user = $this->enrollmentService->enroll($studentData, $sectionId);
            } else {
                $studentId = $all['student_id'];
                $existingUser = User::find($studentId);
                if (!$existingUser) {
                    return response()->json([
                        'message' => 'El estudiante seleccionado no existe'
                    ], 404);
                }
                $user = $this->enrollmentService->enroll(null, $sectionId, $existingUser);
            }

            return response()->json([
                'data' => new UserResource($user),
                'message' => $isNew
                    ? 'Estudiante matriculado exitosamente'
                    : 'Estudiante re-matriculado exitosamente',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * GET /admin/enrollments/available-sections
     * Obtener secciones con cupo disponible para el wizard.
     */
    public function availableSections(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $schoolYearId = $request->query('school_year_id');
        $gradeId = $request->query('grade_id');

        $sections = $this->enrollmentService->getAvailableSections($schoolYearId, $gradeId);

        return response()->json(['data' => $sections]);
    }

    /**
     * GET /admin/enrollments/check-student/{studentId}
     * Verificar matrícula existente de un estudiante en el año activo.
     */
    public function checkStudent(Request $request, int $studentId)
    {
        $this->authorize('viewAny', User::class);

        $activeYear = \App\Models\SchoolYear::where('active', true)->first();
        if (!$activeYear) {
            return response()->json([
                'message' => 'No hay año lectivo activo'
            ], 400);
        }

        $existing = $this->enrollmentService->checkExistingEnrollment($studentId, $activeYear->id);

        return response()->json([
            'data' => [
                'has_enrollment' => $existing !== null,
                'current_enrollment' => $existing,
            ],
        ]);
    }
}
