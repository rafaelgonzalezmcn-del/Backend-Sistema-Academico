<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use App\Models\Section;
use App\Models\Subject;
use App\Services\ActaService;
use App\Services\ExportadorActa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Descarga del acta de calificaciones de una materia en una sección.
 *
 * GET /api/subjects/{subject}/sections/{section}/acta?formato=pdf|xlsx
 *
 * Pueden descargarla el admin y el profesor que dicta la materia en esa sección.
 */
class ActaController extends Controller
{
    public function __construct(
        private ActaService $actaService,
        private ExportadorActa $exportador,
    ) {}

    public function descargar(Request $request, Subject $subject, Section $section)
    {
        $validated = $request->validate([
            'formato' => 'nullable|in:pdf,xlsx',
        ], [
            'formato.in' => 'El formato debe ser pdf o xlsx',
        ]);

        $user = $request->user();
        $dictaElCurso = ClassSchedule::where('subject_id', $subject->id)
            ->where('section_id', $section->id)
            ->where('teacher_id', $user->id)
            ->exists();

        if (!$user->isAdmin() && !($user->isTeacher() && $dictaElCurso)) {
            return response()->json(['message' => 'No tienes permiso para descargar el acta de este curso'], 403);
        }

        if (!ClassSchedule::where('subject_id', $subject->id)->where('section_id', $section->id)->exists()) {
            return response()->json(['message' => 'Esta materia no se dicta en la sección indicada'], 422);
        }

        $acta = $this->actaService->generar($subject, $section);
        $nombre = 'acta-' . Str::slug("{$acta['materia']} {$acta['grado']} {$acta['seccion']} {$acta['anio_lectivo']}")
            . ($acta['cerrado'] ? '' : '-provisional');

        if (($validated['formato'] ?? 'pdf') === 'xlsx') {
            return response()->download(
                $this->exportador->excel($acta),
                "{$nombre}.xlsx",
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )->deleteFileAfterSend();
        }

        return response($this->exportador->pdf($acta), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$nombre}.pdf\"",
        ]);
    }
}
