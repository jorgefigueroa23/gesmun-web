<?php

namespace App\Console\Commands;

use App\Events\DeclaracionesActualizadas;
use App\Events\SesionesActualizadas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Las solicitudes DJ y las sesiones las crean otras aplicaciones directamente
 * en SQL Server, así que Laravel consulta la base cada pocos segundos y emite
 * un evento por WebSocket únicamente cuando algo cambió.
 */
class VigilarPanel extends Command
{
    protected $signature = 'panel:vigilar {--cada=2 : Segundos entre consultas}';

    protected $description = 'Vigila DJ pendientes y sesiones activas y emite eventos por WebSocket al detectar cambios';

    public function handle(): int
    {
        $cada = max(1, (int) $this->option('cada'));

        $huellaDj = null;
        $huellaSesiones = null;

        $this->info("Vigilando cambios cada {$cada}s (Ctrl+C para detener)");

        while (true) {
            try {
                // Solo cuenta qué solicitudes están pendientes, no cuánto llevan esperando
                $dj = md5(json_encode(DB::select(
                    'SELECT AUTDJ_N_CODIGO FROM dbo.TG_AUTORIZACION_DJ WHERE AUTDJ_N_ESTADO = 0 ORDER BY AUTDJ_N_CODIGO'
                )));

                // Solo qué sesiones están abiertas, ignora el latido de ULTIMA_ACTIVIDAD
                $ses = md5(json_encode(DB::select(
                    'SELECT SESSION_ID FROM dbo.TG_USUARIO_SESION WHERE FECHA_CIERRE IS NULL ORDER BY SESSION_ID'
                )));

                // La primera vuelta solo toma la foto inicial
                if ($huellaDj !== null && $dj !== $huellaDj) {
                    DeclaracionesActualizadas::dispatch();
                    $this->line(now()->format('H:i:s').' DJ actualizadas');
                }

                if ($huellaSesiones !== null && $ses !== $huellaSesiones) {
                    SesionesActualizadas::dispatch();
                    $this->line(now()->format('H:i:s').' Sesiones actualizadas');
                }

                $huellaDj = $dj;
                $huellaSesiones = $ses;
            } catch (\Throwable $e) {
                $this->error(now()->format('H:i:s').' '.$e->getMessage());
            }

            sleep($cada);
        }
    }
}
