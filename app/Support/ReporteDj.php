<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Filtros y consulta compartidos por el reporte en pantalla y sus exportaciones.
 */
class ReporteDj
{
    public const PERIODOS = ['hoy', 'ayer', 'mes', 'personalizado'];

    public const COLUMNAS = 'AUTDJ_N_CODIGO, USUARIO, USUARIO_NOMBRE, AUTDJ_T_ACCION, AUTDJ_T_ORIGEN, ESTADO_DESC,
        AUTDJ_N_ESTADO, AUTDJ_D_SOLICITUD, AUTDJ_D_RESPUESTA, SEGUNDOS_ESPERA, SUPERVISOR, SUPERVISOR_NOMBRE,
        CONTRI_N_CONSIS, HOSTNAME, IP, AUTDJ_T_OBSERV';

    /** Devuelve [desde, hasta] (Y-m-d) según el período elegido. */
    public static function rango(string $periodo, string $desde = '', string $hasta = ''): array
    {
        return match ($periodo) {
            'ayer' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'mes' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'personalizado' => [$desde, $hasta],
            default => [now()->toDateString(), now()->toDateString()],
        };
    }

    private static function ymd(string $fecha): ?string
    {
        try {
            return $fecha === '' ? null : Carbon::parse($fecha)->format('Ymd');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Arma el WHERE y sus parámetros a partir de los filtros. */
    public static function condicion(array $f): array
    {
        [$desde, $hasta] = self::rango($f['periodo'] ?? 'hoy', $f['desde'] ?? '', $f['hasta'] ?? '');

        $where = ['1 = 1'];
        $params = [];

        if (($f['estado'] ?? '') !== '') {
            $where[] = 'AUTDJ_N_ESTADO = ?';
            $params[] = (int) $f['estado'];
        }

        // Formato Ymd + estilo 112: SQL Server lo interpreta igual sin importar el idioma de la base
        if ($desde = self::ymd($desde)) {
            $where[] = 'AUTDJ_D_SOLICITUD >= CONVERT(DATETIME, ?, 112)';
            $params[] = $desde;
        }

        if ($hasta = self::ymd($hasta)) {
            $where[] = 'AUTDJ_D_SOLICITUD < DATEADD(DAY, 1, CONVERT(DATETIME, ?, 112))';
            $params[] = $hasta;
        }

        foreach ([
            'usuario' => ['(USUARIO LIKE ? OR USUARIO_NOMBRE LIKE ?)', 2],
            'supervisor' => ['(SUPERVISOR LIKE ? OR SUPERVISOR_NOMBRE LIKE ?)', 2],
            'contribuyente' => ['CAST(CONTRI_N_CONSIS AS VARCHAR(30)) LIKE ?', 1],
        ] as $campo => [$sql, $veces]) {
            if (($f[$campo] ?? '') !== '') {
                $where[] = $sql;
                array_push($params, ...array_fill(0, $veces, '%' . $f[$campo] . '%'));
            }
        }

        return [implode(' AND ', $where), $params];
    }

    /** Todos los registros que cumplen los filtros (para exportar). */
    public static function todos(array $f, int $limite = 20000): array
    {
        [$condicion, $params] = self::condicion($f);

        return DB::select(
            'SELECT TOP (' . $limite . ') ' . self::COLUMNAS . "
             FROM dbo.VW_TG_AUTORIZACION_DJ_REPORTE
             WHERE {$condicion}
             ORDER BY AUTDJ_D_SOLICITUD DESC",
            $params
        );
    }
}
