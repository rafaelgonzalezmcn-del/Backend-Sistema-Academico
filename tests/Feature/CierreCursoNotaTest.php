<?php

/**
 * El cierre de curso debe usar el MISMO cálculo que "Mis notas":
 * parciales con sus parámetros y porcentajes; aprueba con 70 % de la nota máxima.
 */

use App\Models\Entrega;
use App\Models\Parcial;
use App\Models\StudentCourse;
use App\Services\CourseClosureService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->e = escenarioAcademico();
    $this->e->parcial->update(['nota_maxima' => 10]);
    $this->pTareas = $this->e->parcial->parametros()->where('tipo', 'tareas')->first();
    $this->pExamenes = $this->e->parcial->parametros()->where('tipo', 'examenes')->first();
    $this->pTareas->update(['porcentaje' => 40]);
    $this->pExamenes->update(['porcentaje' => 60]);

    $this->nota = function ($estudiante, $parametro, int $puntaje, $nota, $parcial = null) {
        $tarea = crearTarea($this->e, [
            'parcial_id' => ($parcial ?? $this->e->parcial)->id,
            'parametro_id' => $parametro->id,
            'puntaje_maximo' => $puntaje,
        ]);
        Entrega::create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id, 'fecha_entrega' => now(), 'nota' => $nota]);
    };
});

test('el caso que antes reprobaba: 7/10 en Mis notas ahora aprueba al cerrar', function () {
    foreach (range(1, 5) as $i) {
        ($this->nota)($this->e->estudiante, $this->pTareas, 10, 10);   // tareas 10/10
    }
    ($this->nota)($this->e->estudiante, $this->pExamenes, 100, 50);    // examen 50/100

    $servicio = app(CourseClosureService::class);
    $r = $servicio->calculateFinalGrade($this->e->estudiante->id, $this->e->materia->id);

    expect($r['total_obtained'])->toEqual(7.0)
        ->and($r['total_possible'])->toEqual(10.0)
        ->and($r['percentage'])->toEqual(70.0)
        ->and($servicio->determineStatus($r))->toBe('aprobado');
});

test('la nota del cierre coincide con la de Mis notas', function () {
    ($this->nota)($this->e->estudiante, $this->pTareas, 10, 8);
    ($this->nota)($this->e->estudiante, $this->pExamenes, 20, 13);

    Sanctum::actingAs($this->e->estudiante);
    $misNotas = $this->getJson("/api/modulos/{$this->e->modulo->id}/notas/mis-notas")->json('meta.nota_final');

    $cierre = app(CourseClosureService::class)->calculateFinalGrade($this->e->estudiante->id, $this->e->materia->id);
    expect($cierre['total_obtained'])->toEqual($misNotas); // 8×40% + 6.5×60% = 3.2 + 3.9 = 7.1
});

test('con 2 parciales sobre 10 la nota es sobre 20 y aprueba con 14', function () {
    // Usa el Parcial 2 que se crea por defecto con el módulo
    $p2 = Parcial::where('modulo_id', $this->e->modulo->id)->where('numero', 2)->first();
    $p2->update(['nota_maxima' => 10]);
    $ex2 = $p2->parametros()->where('tipo', 'examenes')->first();
    $ex2->update(['porcentaje' => 100]);

    ($this->nota)($this->e->estudiante, $this->pExamenes, 10, 10);       // P1: 10 × 60 % = 6.0
    ($this->nota)($this->e->estudiante, $ex2, 10, 8, $p2);                // P2: 8 × 100 % = 8.0

    $servicio = app(CourseClosureService::class);
    $r = $servicio->calculateFinalGrade($this->e->estudiante->id, $this->e->materia->id);

    expect($r['total_obtained'])->toEqual(14.0)
        ->and($r['total_possible'])->toEqual(20.0)
        ->and($r['percentage'])->toEqual(70.0)
        ->and($servicio->determineStatus($r))->toBe('aprobado')
        ->and($r['parciales'])->toHaveCount(2);
});

test('por debajo del 70 % reprueba', function () {
    ($this->nota)($this->e->estudiante, $this->pTareas, 10, 10);   // 4.0
    ($this->nota)($this->e->estudiante, $this->pExamenes, 10, 4);  // 2.4 → 6.4 / 10

    $servicio = app(CourseClosureService::class);
    $r = $servicio->calculateFinalGrade($this->e->estudiante->id, $this->e->materia->id);
    expect($r['percentage'])->toEqual(64.0)
        ->and($servicio->determineStatus($r))->toBe('reprobado');
});

test('sin ninguna calificación el estado es concluido', function () {
    $servicio = app(CourseClosureService::class);
    $r = $servicio->calculateFinalGrade($this->e->estudiante->id, $this->e->materia->id);
    expect($r)->toBeNull()
        ->and($servicio->determineStatus($r))->toBe('concluido');
});

test('cerrar el curso por API guarda la nota ponderada en el historial', function () {
    foreach (range(1, 5) as $i) {
        ($this->nota)($this->e->estudiante, $this->pTareas, 10, 10);
    }
    ($this->nota)($this->e->estudiante, $this->pExamenes, 100, 50);

    Sanctum::actingAs($this->e->profesor);
    $this->postJson("/api/subjects/{$this->e->materia->id}/sections/{$this->e->seccionA->id}/close")->assertOk();

    $registro = StudentCourse::where('student_id', $this->e->estudiante->id)->first();
    expect((float) $registro->total_score_obtained)->toEqual(7.0)
        ->and((float) $registro->total_score_possible)->toEqual(10.0)
        ->and((float) $registro->final_grade)->toEqual(70.0)
        ->and($registro->status)->toBe('aprobado')
        ->and($registro->parcial_grades[0]['nota'])->toEqual(7.0);
});

test('un parcial sin tareas (ej.: el Parcial 2 por defecto sin usar) no cuenta en la nota', function () {
    // El módulo trae Parcial 2 sobre 100 por defecto, sin tareas
    ($this->nota)($this->e->estudiante, $this->pExamenes, 10, 10); // P1: 6.0 / 10

    $r = app(CourseClosureService::class)->calculateFinalGrade($this->e->estudiante->id, $this->e->materia->id);
    expect($r['total_possible'])->toEqual(10.0)
        ->and($r['parciales'])->toHaveCount(1);
});

test('un parcial con tareas pero sin notas del estudiante sí cuenta (como 0)', function () {
    $p2 = Parcial::where('modulo_id', $this->e->modulo->id)->where('numero', 2)->first();
    $p2->update(['nota_maxima' => 10]);
    $ex2 = $p2->parametros()->where('tipo', 'examenes')->first();
    $ex2->update(['porcentaje' => 100]);

    ($this->nota)($this->e->estudiante, $this->pExamenes, 10, 10);           // P1: 6.0
    ($this->nota)($this->e->companero, $ex2, 10, 9, $p2);                    // P2 tiene tarea, pero el estudiante no tiene nota

    $r = app(CourseClosureService::class)->calculateFinalGrade($this->e->estudiante->id, $this->e->materia->id);
    expect($r['total_obtained'])->toEqual(6.0)
        ->and($r['total_possible'])->toEqual(20.0)
        ->and($r['parciales'][1]['nota'])->toBeNull();
});
