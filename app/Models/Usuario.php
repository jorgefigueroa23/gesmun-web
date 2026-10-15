<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

// class Usuario extends Model
class Usuario extends Authenticatable
{
    protected $table = 'dbo.TG_USUARIO';

    protected $primaryKey = 'USUARI_N_CODIGO';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = [
        'USUARI_T_CLAUSU',
    ];

    public function initials(): string
    {
        return strtoupper(
            mb_substr($this->USUARI_T_CODUSU ?? '', 0, 2)
        );
    }

    public function getNameAttribute(): string
    {
        return $this->USUARI_T_CODUSU ?? '';
    }
}
