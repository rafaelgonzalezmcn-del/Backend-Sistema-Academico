<?php

use App\Models\Grade;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('CRUD SchoolYears - Admin', function () {
    test('admin puede listar años lectivos', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        SchoolYear::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/school-years');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'start_date', 'end_date', 'active']
                ],
                'meta' => ['current_page', 'last_page', 'total']
            ]);
    });

    test('admin puede crear año lectivo', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $schoolYearData = [
            'name' => 'Año Lectivo 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'active' => true,
        ];

        $response = $this->postJson('/api/school-years', $schoolYearData);

        $response->assertStatus(201);
    });

    test('admin puede ver año lectivo específico', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/school-years/{$schoolYear->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $schoolYear->id)
            ->assertJsonPath('data.name', $schoolYear->name);
    });

    test('admin puede actualizar año lectivo', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $schoolYear = SchoolYear::factory()->create(['name' => 'Viejo']);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/school-years/{$schoolYear->id}", [
            'name' => 'Año Actualizado'
        ]);

        $response->assertStatus(200);
    });

    test('admin puede activar año lectivo', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $activeYear = SchoolYear::factory()->create(['active' => true]);
        $newYear = SchoolYear::factory()->create(['active' => false]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/school-years/{$newYear->id}/activate");

        $response->assertStatus(200);
    });

    test('solo un año lectivo puede estar activo', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        $year1 = SchoolYear::factory()->create(['active' => true]);
        $year2 = SchoolYear::factory()->create(['active' => false]);

        Sanctum::actingAs($admin);

        $this->putJson("/api/school-years/{$year2->id}/activate")->assertOk();

        // El año activado queda activo y el anterior se desactiva
        expect($year2->fresh()->active)->toBeTrue()
            ->and($year1->fresh()->active)->toBeFalse()
            ->and(SchoolYear::where('active', true)->count())->toBe(1);
    });
});

describe('Validaciones SchoolYear', function () {
    test('crear año lectivo sin nombre falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/school-years', [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response->assertStatus(422);
    });

    test('crear año lectivo con nombre duplicado falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        $existingYear = SchoolYear::factory()->create(['name' => 'Duplicado']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/school-years', [
            'name' => 'Duplicado',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response->assertStatus(422);
    });

    test('crear año lectivo con end_date antes de start_date falla', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/school-years', [
            'name' => 'Año Inválido',
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(422);
    });

    test('profesor no puede crear año lectivo', function () {
        $profesorRole = Role::where('name', 'profesor')->first();
        $profesor = User::factory()->create(['role_id' => $profesorRole->id, 'activo' => true]);

        Sanctum::actingAs($profesor);

        $response = $this->postJson('/api/school-years', [
            'name' => 'Año Nuevo',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response->assertStatus(403);
    });

    test('estudiante no puede crear año lectivo', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);

        Sanctum::actingAs($estudiante);

        $response = $this->postJson('/api/school-years', [
            'name' => 'Año Nuevo',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response->assertStatus(403);
    });
});

describe('SchoolYear - Usuario autenticado', function () {
    test('usuario puede obtener año lectivo activo', function () {
        $role = Role::where('name', 'estudiante')->first();
        $user = User::factory()->create(['role_id' => $role->id, 'activo' => true]);
        
        $activeYear = SchoolYear::factory()->create(['active' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/school-years/active');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $activeYear->id)
            ->assertJsonPath('data.name', $activeYear->name);
    });

    test('retorna 404 cuando no hay año activo', function () {
        $role = Role::where('name', 'estudiante')->first();
        $user = User::factory()->create(['role_id' => $role->id, 'activo' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/school-years/active');

        $response->assertStatus(404);
    });
});

describe('SchoolYear - Relaciones', function () {
    test('año lectivo tiene secciones relacionadas', function () {
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create([
            'grade_id' => $grade->id,
            'school_year_id' => $schoolYear->id
        ]);

        $this->assertTrue($schoolYear->sections->contains($section));
    });
});

describe('SchoolYear - Filtros', function () {
    test('puede filtrar solo años activos', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);

        SchoolYear::factory()->count(2)->create(['active' => true]);
        SchoolYear::factory()->count(3)->create(['active' => false]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/school-years?only_active=true');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    });
});