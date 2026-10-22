<?php

use Illuminate\Support\Facades\Broadcast;

// Versión anterior:
// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

// El sistema autentica con App\Models\Usuario (clave primaria USUARI_N_CODIGO)
Broadcast::channel('App.Models.Usuario.{id}', function ($usuario, $id) {
    return (int) $usuario->USUARI_N_CODIGO === (int) $id;
});

// Canal del panel (DJ y sesiones): cualquier usuario autenticado
Broadcast::channel('panel', function ($usuario) {
    return $usuario !== null;
});
