<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'department',
        'position',
        'phone',
    ];

    /**
     * Obtener el usuario asociado al empleado.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}