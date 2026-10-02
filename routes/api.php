<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SchoolYearController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminAcademicHistoryController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\ParcialController;
use App\Http\Controllers\EntregaController;
use App\Models\Role;
use App\Models\User;
/*
|--------------------------------------------------------------------------
| Rutas de Sanctum (CSRF Cookie)
|--------------------------------------------------------------------------
*/
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Archivos privados (materiales, tareas, entregas)
|--------------------------------------------------------------------------
| Solo funciona con una URL firmada y temporal que el backend genera después
| de verificar permisos (ver App\Support\ArchivoPrivado).
*/
Route::get('/archivos', [\App\Http\Controllers\ArchivoController::class, 'ver'])
    ->middleware(['signed', 'throttle:120,1'])
    ->name('archivos.ver');

/*
|--------------------------------------------------------------------------
| Rutas Públicas
|--------------------------------------------------------------------------
*/
// Login más restrictivo - 10 requests/minuto para prevenir brute force
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Logout - menor throttle (5/min) para evitar abuso de sesiones
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| School Years - Lectura pública
|--------------------------------------------------------------------------
*/
Route::get('/school-years/active', [SchoolYearController::class, 'active']);
Route::get('/school-years', [SchoolYearController::class, 'index']);
Route::get('/school-years/{schoolYear}', [SchoolYearController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Horarios - Consulta pública (SIN autenticación)
|--------------------------------------------------------------------------
| NOTA: Estas rutas son públicas por diseño - permiten a padres, estudiantes
| y visitantes ver horarios sin necesidad de login.
| Si se requiere autenticación, agregar middleware 'auth:sanctum'.
*/
Route::get('/sections/{id}/schedule', [ClassScheduleController::class, 'sectionSchedule']);
Route::get('/teachers/{id}/schedule', [ClassScheduleController::class, 'teacherSchedule']);
Route::get('/students/{sectionId}/schedule', [ClassScheduleController::class, 'studentSchedule']);

/*
|--------------------------------------------------------------------------
| Materias - Solo usuarios autenticados
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/subjects/{subject}', [SubjectController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Participantes - Solo usuarios autenticados
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    Route::get('/subjects/{subject}/participantes', [SubjectController::class, 'participantes']);
});

/*
|--------------------------------------------------------------------------
| Grados y Secciones - Lectura pública
|--------------------------------------------------------------------------
*/
Route::get('/grades', [GradeController::class, 'index']);
Route::get('/grades/{grade}', [GradeController::class, 'show']);
Route::get('/sections', [SectionController::class, 'index']);
Route::get('/sections/{section}', [SectionController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Rutas Autenticadas - Todos los roles
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    // Información del usuario actual
    Route::get('/me', function (Request $request) {
        return response()->json(['data' => $request->user()->load(['role', 'section'])]);
    });

    // Actualizar perfil del usuario autenticado
    Route::put('/profile', [UserController::class, 'updateProfile']);

    // Selfie (foto de perfil en BD)
    Route::post('/profile/selfie', [UserController::class, 'uploadSelfie']);
    Route::get('/profile/selfie', [UserController::class, 'getSelfie']);
    Route::delete('/profile/selfie', [UserController::class, 'deleteSelfie']);
    Route::get('/users/{user}/selfie', [UserController::class, 'getUserSelfie']);

    // Mi horario según rol
    Route::get('/my-schedule', [ClassScheduleController::class, 'mySchedule']);

    // Mis materias (profesor) - solo profesor
    Route::middleware(['role:profesor'])->group(function () {
        Route::get('/my-subjects', [SubjectController::class, 'mySubjects']);
    });
    
    // Mis materias (estudiante) - solo estudiante
    Route::middleware(['role:estudiante'])->group(function () {
        Route::get('/my-subjects-student', [SubjectController::class, 'mySubjectsForStudent']);
    });

    // Historial académico del estudiante (todos los roles autenticados)
    Route::get('/students/{student}/courses', [SubjectController::class, 'getStudentCourses']);

        // Entregas - Unir profesor y admin en un solo middleware
        Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
        // Estudiantes: mi-entrega PRIMERO (más específico)
        Route::middleware(['role:estudiante'])->group(function () {
            Route::get('/entregas/{tarea}/mi-entrega', [App\Http\Controllers\EntregaController::class, 'show']);
            Route::post('/entregas/{tarea}', [App\Http\Controllers\EntregaController::class, 'store']);
            Route::delete('/entregas/{entrega}', [App\Http\Controllers\EntregaController::class, 'destroy']);
        });
        
        // Profesores y Admins: descargar y calificar (DEBE estar antes de {tarea})
        Route::middleware(['role:profesor,admin'])->group(function () {
            Route::get('/entregas/descargar/{entrega}', [App\Http\Controllers\EntregaController::class, 'download']);
            // Ruta para calificar entrega existente
            Route::put('/entregas/calificar/{entrega}', [App\Http\Controllers\EntregaController::class, 'calificar']);
            // Ruta para calificar directamente con tarea_id y estudiante_id (endpoint separado para evitar conflictos)
            Route::post('/calificar-estudiante', [App\Http\Controllers\EntregaController::class, 'calificarDirecto']);
        });
        
        // index endpoint - todos los roles autenticados pueden acceder (DEBE ser último)
        // El controlador decide qué datos retornar según el rol
        Route::get('/entregas/{tarea}', [App\Http\Controllers\EntregaController::class, 'index']);
    });
});

/*
|--------------------------------------------------------------------------
| CONTEOS - Accesible para Admin y Profesor
|--------------------------------------------------------------------------
| Endpoints de conteo (count_only) que requieren autenticación pero no rol específico
*/
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    // Conteo de horarios - accesible para admin y profesor
    Route::get('/class-schedules/count', [ClassScheduleController::class, 'count']);
});

/*
|--------------------------------------------------------------------------
| ADMIN - Gestión del Sistema
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:60,1'])->group(function () {
    // Roles (listar todos)
    Route::get('/roles', function () {
        return response()->json(['data' => Role::all()]);
    });

    // Profesores (listar todos)
    Route::get('/teachers', function () {
        return response()->json(['data' =>
            User::whereHas('role', fn($q) => $q->where('name', 'profesor'))
                ->where('activo', true)
                ->get(['id', 'first_name', 'last_name', 'email'])
        ]);
    });
    
    // Usuarios (CRUD completo - solo admin)
    Route::apiResource('users', UserController::class)->except(['show']);
    Route::patch('/users/{user}/assign-section', [UserController::class, 'assignSection']);

    // Conteo de usuarios (solo admin)
    Route::get('/users/count', [UserController::class, 'count']);
});

// Operaciones masivas de usuarios (solo admin) - más restrictivo para prevenir abuso
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::patch('/admin/users/batch-assign-section', [UserController::class, 'batchAssignSection']);
    Route::patch('/admin/users/batch-deactivate', [UserController::class, 'batchDeactivate']);
    Route::post('/admin/users/batch-preview', [UserController::class, 'batchPreview']);
});

// Validaciones en tiempo real (solo admin) - más restrictivo
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::get('/users/check-identification', [UserController::class, 'checkIdentification']);
    Route::get('/users/check-email', [UserController::class, 'checkEmail']);
    Route::get('/users/check-availability', [UserController::class, 'checkAvailability']);
});

// Historial académico (solo admin)
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:60,1'])->group(function () {
    Route::get('/admin/students/{student}/academic-history', [AdminAcademicHistoryController::class, 'studentHistory']);
    Route::get('/admin/sections/{section}/academic-summary', [AdminAcademicHistoryController::class, 'sectionSummary']);
});

// Dashboard de métricas (solo admin) - menos restrictivo ya que está cacheado
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:120,1'])->group(function () {
    Route::get('/admin/dashboard/stats', [AdminDashboardController::class, 'stats']);
});

// Matrícula (solo admin) - operaciones críticas
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::post('/admin/enrollments', [\App\Http\Controllers\EnrollmentController::class, 'enroll']);
    Route::get('/admin/enrollments/available-sections', [\App\Http\Controllers\EnrollmentController::class, 'availableSections']);
    Route::get('/admin/enrollments/check-student/{student}', [\App\Http\Controllers\EnrollmentController::class, 'checkStudent']);
});

// Elegibilidad y promoción (solo admin) - operaciones críticas con límites más altos para batch
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:60,1'])->group(function () {
    Route::get('/admin/students/{student}/promotion-eligibility', [PromotionController::class, 'checkEligibility']);
    Route::post('/admin/promotions/check-batch-eligibility', [PromotionController::class, 'checkBatchEligibility']);
    Route::post('/admin/students/{student}/promote', [PromotionController::class, 'promoteStudent']);
    Route::post('/admin/sections/{section}/promote-all', [PromotionController::class, 'promoteSection']);
});

// Años lectivos
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::post('/school-years', [SchoolYearController::class, 'store']);
    Route::put('/school-years/{schoolYear}', [SchoolYearController::class, 'update']);
    Route::delete('/school-years/{schoolYear}', [SchoolYearController::class, 'destroy']);
    Route::put('/school-years/{id}/activate', [SchoolYearController::class, 'activate']);
});

// Grados
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::post('/grades', [GradeController::class, 'store']);
    Route::put('/grades/{grade}', [GradeController::class, 'update']);
    Route::delete('/grades/{grade}', [GradeController::class, 'destroy']);
});

// Secciones
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::post('/sections', [SectionController::class, 'store']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
});

// Materias
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::put('/subjects/{subject}', [SubjectController::class, 'update']);
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);
});

// Horarios (CRUD completo solo admin)
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:30,1'])->group(function () {
    Route::apiResource('class-schedules', ClassScheduleController::class);
});

// Activity Log
Route::middleware(['auth:sanctum', 'role:admin', 'throttle:60,1'])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/count', [ActivityLogController::class, 'count']);
});

/*
|--------------------------------------------------------------------------
| Ver usuario específico - Autenticados (usa Policy)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    Route::get('/users/{user}', [UserController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| PROFESOR - Módulos y Materiales
|--------------------------------------------------------------------------
*/
// Rutas de lectura (profesor y estudiante)
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    Route::get('/materias/{materiaId}/modulos', [ModuloController::class, 'index']);
    Route::get('/materiales/{material}/descargar', [ModuloController::class, 'downloadMaterial']);
    // Tareas - lectura
    Route::get('/modulos/{moduloId}/tareas', [TareaController::class, 'index']);
    Route::get('/tareas/{tarea}', [TareaController::class, 'show']);
    Route::get('/tareas/{tarea}/descargar', [TareaController::class, 'downloadArchivo']);
    // Tareas por parámetro
    Route::get('/parametros/{parametroId}/tareas', [TareaController::class, 'tareasPorParametro']);
    // Parciales y parámetros - lectura
    Route::get('/modulos/{moduloId}/parciales', [ParcialController::class, 'index']);
    Route::get('/parciales/{parcialId}/parametros', [ParcialController::class, 'parametros']);
    // Notas
    Route::get('/modulos/{moduloId}/notas/resumen', [ParcialController::class, 'resumenNotas']);
    Route::get('/modulos/{moduloId}/notas/mis-notas', [ParcialController::class, 'misNotas']);
    // Forzar creación de parciales (endpoint auxiliar)
    Route::post('/modulos/{moduloId}/parciales/crear', [ParcialController::class, 'forzarCreacion']);
});

// Rutas de modificación (profesor y admin)
Route::middleware(['auth:sanctum', 'active', 'role:profesor,admin', 'throttle:60,1'])->group(function () {
    // CRUD de módulos
    Route::post('/modulos', [ModuloController::class, 'store']);
    Route::put('/modulos/{modulo}', [ModuloController::class, 'update']);
    Route::delete('/modulos/{modulo}', [ModuloController::class, 'destroy']);
    
    // Materiales
    Route::post('/modulos/{modulo}/materiales', [ModuloController::class, 'uploadMaterial']);
    Route::delete('/materiales/{material}', [ModuloController::class, 'destroyMaterial']);

    // ================================================
    // CIERRE DE CURSO - Profesor y Admin (validado en controller)
    // ================================================
});

// Cierre de curso — accesible para profesor Y admin
// La validación real se hace en el controller (verifica profesor OR admin)
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    // Verificar estado del curso
    Route::get('/subjects/{subject}/sections/{section}/status', [SubjectController::class, 'getCourseStatus']);
    
    // Cerrar curso masivo para una materia+sección
    Route::post('/subjects/{subject}/sections/{section}/close', [SubjectController::class, 'closeCourse']);
    
    // Cerrar curso para un estudiante específico
    Route::post('/students/{student}/subjects/{subject}/sections/{section}/close', [SubjectController::class, 'closeStudentCourse']);
});

// Admin: listar cursos pendientes de cierre
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {
    Route::get('/admin/courses/pending-closure', [SubjectController::class, 'listPendingClosures']);
});

// Dashboard del profesor (solo profesor)
Route::middleware(['auth:sanctum', 'active', 'role:profesor', 'throttle:60,1'])->group(function () {
    Route::get('/teacher/dashboard', [App\Http\Controllers\TeacherDashboardController::class, 'dashboard']);
    Route::get('/teacher/courses/stats', [App\Http\Controllers\TeacherDashboardController::class, 'courseStats']);
    
    // Tareas - escritura
    Route::post('/tareas', [TareaController::class, 'store']);
    Route::put('/tareas/{tarea}', [TareaController::class, 'update']);
    Route::delete('/tareas/{tarea}', [TareaController::class, 'destroy']);
    
    // Parciales y parámetros
    Route::post('/parciales', [ParcialController::class, 'store']);
    Route::put('/parciales/{parcial}', [ParcialController::class, 'update']);
    Route::post('/parciales/{parcialId}/parametros', [ParcialController::class, 'storeParametro']);
    Route::put('/parametros/{parametro}', [ParcialController::class, 'updateParametro']);
    Route::delete('/parametros/{parametro}', [ParcialController::class, 'destroyParametro']);
});
