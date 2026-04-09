<?php

namespace App\Http\Controllers;

use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    protected PromotionService $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    /**
     * GET /admin/students/{student}/promotion-eligibility
     * Verificar elegibilidad de promoción para un estudiante.
     * Solo accesible para admin.
     */
    public function checkEligibility(int $studentId, Request $request)
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $schoolYearId = $request->query('school_year_id');

        $result = $this->promotionService->checkEligibility($studentId, $schoolYearId);

        return response()->json(['data' => $result]);
    }

    /**
     * POST /admin/promotions/check-batch-eligibility
     * Verificar elegibilidad de múltiples estudiantes en una sola petición.
     * Solo accesible para admin.
     */
    public function checkBatchEligibility(Request $request)
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|exists:users,id',
            'school_year_id' => 'nullable|integer|exists:school_years,id',
        ]);

        $results = [];
        foreach ($request->student_ids as $studentId) {
            $results[$studentId] = $this->promotionService->checkEligibility(
                $studentId,
                $request->query('school_year_id')
            );
        }

        return response()->json(['data' => $results]);
    }

    /**
     * POST /admin/students/{student}/promote
     * Promover un estudiante al siguiente grado o repetir grado.
     * Solo accesible para admin.
     */
    public function promoteStudent(int $studentId, Request $request)
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $request->validate([
            'target_section_id' => 'required|integer|exists:sections,id',
            'move_type' => 'required|in:promote,repeat',
            'observations' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->promotionService->promoteStudent(
                $studentId,
                $request->target_section_id,
                $request->move_type,
                $request->observations
            );

            return response()->json([
                'data' => $result,
                'message' => $request->move_type === 'repeat'
                    ? 'Estudiante asignado para repetir grado exitosamente'
                    : 'Estudiante promocionado exitosamente',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /admin/sections/{section}/promote-all
     * Promover masivamente todos los estudiantes elegibles de una sección.
     * Solo accesible para admin.
     */
    public function promoteSection(int $sectionId, Request $request)
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $request->validate([
            'target_grade_id' => 'required|integer|exists:grades,id',
            'move_type' => 'required|in:promote,repeat',
            'observations' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->promotionService->promoteSection(
                $sectionId,
                $request->target_grade_id,
                $request->move_type,
                $request->observations
            );

            return response()->json([
                'data' => $result,
                'message' => "Promoción masiva completada: {$result['promoted']} promovidos, {$result['skipped']} saltados",
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
