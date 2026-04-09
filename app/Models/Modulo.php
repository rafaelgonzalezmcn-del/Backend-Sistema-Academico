<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modulo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modulos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'materia_id'
    ];

    /**
     * Relación con materia
     */
    public function materia()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Relación con materiales
     */
    public function materiales()
    {
        return $this->hasMany(Material::class);
    }

    /**
     * Obtener número de materiales
     */
    public function getNumMaterialesAttribute()
    {
        return $this->materiales()->count();
    }
}
