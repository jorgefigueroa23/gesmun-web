<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Actualización automática del panel de Declaraciones Juradas
    |--------------------------------------------------------------------------
    |
    | reverb  : tiempo real por WebSocket (requiere reverb:start y panel:vigilar)
    | polling : cada PC consulta al servidor cada PANEL_POLL_SEGUNDOS (no requiere nada más)
    | manual  : sin actualización automática, solo con el botón "Recargar"
    |
    */

    'actualizacion' => env('PANEL_ACTUALIZACION', 'reverb'),

    'poll_segundos' => max(1, (int) env('PANEL_POLL_SEGUNDOS', 3)),

];
