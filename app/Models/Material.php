<?php

namespace App\Models;

use App\Support\ArchivoPrivado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'materiales';

    protected $fillable = [
        'nombre_archivo',
        'ruta',
        'tipo_archivo',
        'tamano',
        'descripcion',
        'modulo_id',
        'user_id'
    ];

    protected $casts = [
        'tamano' => 'integer',
    ];

    protected $appends = ['url'];

    /**
     * Relación con módulo
     */
    public function modulo()
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Relación con usuario (profesor que subió)
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Obtener URL completa del archivo
     */
    public function getUrlAttribute()
    {
        return ArchivoPrivado::url($this->ruta);
    }

    /**
     * Formatear tamaño
     */
    public function getTamanoFormateadoAttribute()
    {
        $bytes = $this->tamano;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
