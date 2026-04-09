<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleAndAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $estudianteRole = Role::firstOrCreate(['name' => 'estudiante']);
    $profesorRole = Role::firstOrCreate(['name' => 'profesor']);

    User::firstOrCreate(
    ['email' => 'admin@admin.com'],
    [
        'first_name' => 'Administrador',
        'last_name' => 'Principal',
        'password' => Hash::make('admin123'),
        'role_id' => $adminRole->id,
        'activo' => true,
    ]
);
}
}