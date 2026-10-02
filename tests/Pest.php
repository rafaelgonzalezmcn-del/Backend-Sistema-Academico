<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use to
| assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
/*
|--------------------------------------------------------------------------
| Escenario académico reutilizable (tareas, entregas, notas)
|--------------------------------------------------------------------------
| Crea: una materia dictada por "profesor" en la sección A, un estudiante
| en la sección A (inscrito) y otro en la sección B (no inscrito), un
| profesor ajeno, un admin, un módulo con sus 2 parciales y parámetros.
*/
function escenarioAcademico(): object
{
    foreach (['admin', 'estudiante', 'profesor'] as $rol) {
        \App\Models\Role::firstOrCreate(['name' => $rol]);
    }

    $seccionA = \App\Models\Section::factory()->create();
    $seccionB = \App\Models\Section::factory()->create();
    $materia = \App\Models\Subject::factory()->create();

    $e = (object) [
        'seccionA' => $seccionA,
        'seccionB' => $seccionB,
        'materia' => $materia,
        'admin' => \App\Models\User::factory()->admin()->create(['activo' => true]),
        'profesor' => \App\Models\User::factory()->profesor()->create(['activo' => true]),
        'otroProfesor' => \App\Models\User::factory()->profesor()->create(['activo' => true]),
        'estudiante' => \App\Models\User::factory()->estudiante()->create(['activo' => true, 'section_id' => $seccionA->id]),
        'companero' => \App\Models\User::factory()->estudiante()->create(['activo' => true, 'section_id' => $seccionA->id]),
        'ajeno' => \App\Models\User::factory()->estudiante()->create(['activo' => true, 'section_id' => $seccionB->id]),
    ];

    \App\Models\ClassSchedule::factory()->create([
        'teacher_id' => $e->profesor->id,
        'subject_id' => $materia->id,
        'section_id' => $seccionA->id,
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $e->modulo = \App\Models\Modulo::create(['nombre' => 'Módulo 1', 'materia_id' => $materia->id]);
    app(\App\Services\ParcialService::class)->asegurarParciales($e->modulo->id);
    $e->parcial = \App\Models\Parcial::where('modulo_id', $e->modulo->id)->where('numero', 1)->first();
    $e->parametro = $e->parcial->parametros()->where('tipo', 'tareas')->first();

    return $e;
}

/** Crea una tarea directamente en BD (para pruebas que no prueban la creación). */
function crearTarea(object $e, array $datos = []): \App\Models\Tarea
{
    return \App\Models\Tarea::create(array_merge([
        'titulo' => 'Tarea de prueba',
        'descripcion' => 'Descripción',
        'fecha_limite' => now()->addDays(3),
        'modulo_id' => $e->modulo->id,
        'puntaje_maximo' => 10,
        'parcial_id' => $e->parcial->id,
        'parametro_id' => $e->parametro->id,
    ], $datos));
}
