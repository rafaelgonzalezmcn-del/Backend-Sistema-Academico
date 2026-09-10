<?php

use App\Models\User;
use App\Models\Role;
use App\Models\Section;
use App\Models\Grade;
use App\Models\SchoolYear;
use App\Services\UserService;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'estudiante']);
    Role::firstOrCreate(['name' => 'profesor']);
});

describe('UserService - create', function () {
    
    test('crea usuario correctamente', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $service = new UserService();
        $user = $service->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@test.com',
            'password' => 'password123',
            'role_id' => $estudianteRole->id,
            'activo' => true
        ]);
        
        expect($user->first_name)->toBe('Juan');
        expect($user->last_name)->toBe('Pérez');
        expect($user->email)->toBe('juan@test.com');
        expect($user->role_id)->toBe($estudianteRole->id);
        expect($user->activo)->toBeTrue();
        $this->assertDatabaseHas('users', ['email' => 'juan@test.com']);
    });
    
    test('normaliza email a minúsculas', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        
        $service = new UserService();
        $user = $service->create([
            'first_name' => 'Juan',
            'email' => 'JUAN@TEST.COM',
            'password' => 'password123',
            'role_id' => $estudianteRole->id
        ]);
        
        expect($user->email)->toBe('juan@test.com');
    });

    test('asigna sección si se proporciona y hay espacio', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'max_capacity' => 30]);
        
        $service = new UserService();
        $user = $service->create([
            'first_name' => 'Juan',
            'email' => 'juan2@test.com',
            'password' => 'password123',
            'role_id' => $estudianteRole->id,
            'section_id' => $section->id
        ]);
        
        expect($user->section_id)->toBe($section->id);
    });

    test('lanza excepción cuando sección está llena', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'max_capacity' => 1]);
        
        // Llenar la sección
        $otherStudent = User::factory()->create(['role_id' => $estudianteRole->id, 'section_id' => $section->id, 'activo' => true]);
        
        $service = new UserService();
        
        expect(function () use ($service, $estudianteRole, $section) {
            $service->create([
                'first_name' => 'Juan',
                'email' => 'juan3@test.com',
                'password' => 'password123',
                'role_id' => $estudianteRole->id,
                'section_id' => $section->id
            ]);
        })->toThrow(\Exception::class);
    });
});

describe('UserService - update', function () {
    test('actualiza usuario correctamente', function () {
        $user = User::factory()->create();
        
        $service = new UserService();
        $updated = $service->update($user, ['first_name' => 'Nuevo Nombre']);
        
        expect($updated->first_name)->toBe('Nuevo Nombre');
    });
    
    test('actualiza email normalizado', function () {
        $user = User::factory()->create();
        
        $service = new UserService();
        $updated = $service->update($user, ['email' => 'NUEVO@TEST.COM']);
        
        expect($updated->email)->toBe('nuevo@test.com');
    });

    test('actualiza role_id correctamente', function () {
        $user = User::factory()->create();
        $profesorRole = Role::where('name', 'profesor')->first();
        
        $service = new UserService();
        $updated = $service->update($user, ['role_id' => $profesorRole->id]);
        
        expect($updated->role_id)->toBe($profesorRole->id);
    });

    test('actualiza campo activo correctamente', function () {
        $user = User::factory()->create(['activo' => true]);
        
        $service = new UserService();
        $updated = $service->update($user, ['activo' => false]);
        
        expect($updated->activo)->toBeFalse();
    });
});

describe('UserService - deactivate', function () {
    test('desactiva usuario correctamente', function () {
        $user = User::factory()->create(['activo' => true]);
        
        $service = new UserService();
        $service->deactivate($user);
        
        $user->refresh();
        expect($user->activo)->toBeFalse();
    });
});

describe('UserService - assignSection', function () {
    test('asigna sección a estudiante correctamente', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true, 'section_id' => null]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id, 'max_capacity' => 30]);
        
        $service = new UserService();
        $service->assignSection($estudiante, $section->id);
        
        $estudiante->refresh();
        expect($estudiante->section_id)->toBe($section->id);
    });

    test('lanza excepción cuando usuario no es estudiante', function () {
        $adminRole = Role::where('name', 'admin')->first();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'activo' => true]);
        
        $schoolYear = SchoolYear::factory()->create();
        $grade = Grade::factory()->create();
        $section = Section::factory()->create(['grade_id' => $grade->id, 'school_year_id' => $schoolYear->id]);
        
        $service = new UserService();
        
        expect(function () use ($service, $admin, $section) {
            $service->assignSection($admin, $section->id);
        })->toThrow(\Exception::class);
    });

    test('lanza excepción cuando sección no existe', function () {
        $estudianteRole = Role::where('name', 'estudiante')->first();
        $estudiante = User::factory()->create(['role_id' => $estudianteRole->id, 'activo' => true]);
        
        $service = new UserService();
        
        expect(function () use ($service, $estudiante) {
            $service->assignSection($estudiante, 99999);
        })->toThrow(\Exception::class);
    });
});

describe('UserService - updateProfile', function () {
    test('actualiza perfil correctamente', function () {
        $user = User::factory()->create();
        
        $service = new UserService();
        $service->updateProfile($user, ['first_name' => 'Nuevo']);
        
        $user->refresh();
        expect($user->first_name)->toBe('Nuevo');
    });
});