<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        p { margin: 0 0 8px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0b1f66; color: #fff; text-align: left; padding: 4px; }
        td { border-bottom: 1px solid #ddd; padding: 3px 4px; vertical-align: top; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }} - Reporte de Declaraciones Juradas</h1>
    <p>Período: {{ $rango }} | Estado: {{ $estado }} | Registros: {{ count($filas) }} | Generado: {{ now()->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>N°</th><th>Usuario</th><th>Acción</th><th>Estado</th><th>Solicitud</th><th>Respuesta</th>
                <th>Espera (seg)</th><th>Supervisor</th><th>Contribuyente</th><th>Equipo / IP</th><th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $r)
                <tr>
                    <td>{{ $r->AUTDJ_N_CODIGO }}</td>
                    <td>{{ $r->USUARIO }}<br>{{ $r->USUARIO_NOMBRE }}</td>
                    <td>{{ $r->AUTDJ_T_ACCION }}</td>
                    <td>{{ $r->ESTADO_DESC }}</td>
                    <td>{{ $r->AUTDJ_D_SOLICITUD }}</td>
                    <td>{{ $r->AUTDJ_D_RESPUESTA }}</td>
                    <td>{{ $r->SEGUNDOS_ESPERA }}</td>
                    <td>{{ $r->SUPERVISOR }}<br>{{ $r->SUPERVISOR_NOMBRE }}</td>
                    <td>{{ $r->CONTRI_N_CONSIS }}</td>
                    <td>{{ $r->HOSTNAME }}<br>{{ $r->IP }}</td>
                    <td>{{ $r->AUTDJ_T_OBSERV }}</td>
                </tr>
            @empty
                <tr><td colspan="11" style="text-align:center">Sin registros</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
