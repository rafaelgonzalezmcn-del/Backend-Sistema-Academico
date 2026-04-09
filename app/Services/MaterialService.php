<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Modulo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Traits\LogsActivity;

class MaterialService
{
    use LogsActivity;
    /**
     * Subir material a un módulo
     * USA TRANSACTION:確保原子性 - si falla DB, archivo se elimina
     */
    public function upload(Modulo $modulo, User $user, array $data): Material
    {
        $file = $data['archivo'];
        
        // Generar nombre único
        $originalName = $file->getClientOriginalName();
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        
        // Guardar en storage
        $path = $file->storeAs('materiales', $fileName, 'public');

        try {
            // Crear registro en DB
            $material = DB::transaction(function () use ($data, $path, $file, $originalName, $modulo, $user) {
                return Material::create([
                    'nombre_archivo' => $data['nombre_personalizado'] ?? $originalName,
                    'ruta' => $path,
                    'tipo_archivo' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                    'descripcion' => $data['descripcion'] ?? null,
                    'modulo_id' => $modulo->id,
                    'user_id' => $user->id
                ]);
            });

            $this->logActivity($this->logEvent('material', 'uploaded'), $material, null, true);

            return $material;

        } catch (\Exception $e) {
            // Si falla DB, eliminar archivo subido
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }

    /**
     * Eliminar material
     */
    public function delete(Material $material): void
    {
        // Eliminar archivo físico
        if ($material->ruta && Storage::disk('public')->exists($material->ruta)) {
            Storage::disk('public')->delete($material->ruta);
        }

        $material->delete();

        $this->logActivity($this->logEvent('material', 'deleted'), $material, null, true);
    }

    /**
     * Obtener información de descarga
     */
    public function getDownloadInfo(Material $material): array
    {
        if (!$material->ruta || !Storage::disk('public')->exists($material->ruta)) {
            return [
                'success' => false,
                'message' => 'Archivo no encontrado'
            ];
        }

        return [
            'success' => true,
            'download_url' => asset('storage/' . $material->ruta),
            'filename' => $material->nombre_archivo
        ];
    }

    /**
     * Verificar que el usuario puede acceder al material
     */
    public function canAccessMaterial(User $user, Material $material): bool
    {
        // Admin tiene acceso total
        if ($user->role && $user->role->name === 'admin') {
            return true;
        }

        // Cargar relación modulo si no está cargada
        if (!$material->relationLoaded('modulo')) {
            $material->load('modulo');
        }

        $materiaId = $material->modulo->materia_id;

        // Profesor debe enseñar la materia
        if ($user->role && $user->role->name === 'profesor') {
            return \App\Models\ClassSchedule::where('teacher_id', $user->id)
                ->whereHas('subject', function ($query) use ($materiaId) {
                    $query->where('id', $materiaId);
                })->exists();
        }

        // Estudiante debe estar inscrito en la materia
        if ($user->role && $user->role->name === 'estudiante' && $user->section_id) {
            return \App\Models\ClassSchedule::where('section_id', $user->section_id)
                ->whereHas('subject', function ($query) use ($materiaId) {
                    $query->where('id', $materiaId);
                })->exists();
        }

        return false;
    }
}
