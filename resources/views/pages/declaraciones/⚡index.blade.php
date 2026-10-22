<?php

use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    /*
    |--------------------------------------------------------------------------
    | Control de Declaraciones Juradas
    |--------------------------------------------------------------------------
    |
    | Lógica tomada de example-app (TestController::pendientesDj /
    | responderDj / reporteDj).
    |
    */

    public string $mensaje = '';

    public string $tipoMensaje = 'success';

    // Filtros del reporte
    public string $desde = '';

    public string $hasta = '';

    public string $estado = '';

    public bool $verReporte = false;


    // Tiempo real: Reverb avisa cuando cambian los datos y se vuelve a consultar
    // Modo configurable en .env (PANEL_ACTUALIZACION): reverb | polling | manual
    protected function getListeners()
    {
        if (config('panel.actualizacion') !== 'reverb') {
            return [];
        }

        return [
            'echo-private:panel,.DeclaracionesActualizadas' => '$refresh',
        ];
    }


    public function with()
    {
        $pendientes = [];
        $reporte = [];

        try {
            // Bandeja del supervisor: solicitudes pendientes
            $pendientes = DB::select(
                'SELECT AUTDJ_N_CODIGO, SESSION_ID, USUARI_N_CODIGO, USUARIO, USUARIO_NOMBRE,
                        HOSTNAME, IP, WINDOWS_USER, CONTRI_N_CONSIS, AUTDJ_D_SOLICITUD, SEGUNDOS_ESPERA
                 FROM dbo.VW_TG_AUTORIZACION_DJ_REPORTE
                 WHERE AUTDJ_N_ESTADO = 0
                 ORDER BY AUTDJ_D_SOLICITUD'
            );

            if ($this->verReporte) {
                $where = [];
                $params = [];

                if ($this->desde !== '') {
                    $where[] = 'AUTDJ_D_SOLICITUD >= ?';
                    $params[] = $this->desde;
                }

                if ($this->hasta !== '') {
                    $where[] = 'AUTDJ_D_SOLICITUD < DATEADD(DAY, 1, CAST(? AS DATE))';
                    $params[] = $this->hasta;
                }

                if (in_array($this->estado, ['0', '1', '2'], true)) {
                    $where[] = 'AUTDJ_N_ESTADO = ?';
                    $params[] = $this->estado;
                }

                $reporte = DB::select(
                    'SELECT TOP (500) AUTDJ_N_CODIGO, USUARIO, USUARIO_NOMBRE, AUTDJ_T_ACCION, ESTADO_DESC,
                            AUTDJ_D_SOLICITUD, AUTDJ_D_RESPUESTA, SEGUNDOS_ESPERA, SUPERVISOR,
                            CONTRI_N_CONSIS, HOSTNAME, IP
                     FROM dbo.VW_TG_AUTORIZACION_DJ_REPORTE'
                    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                    . ' ORDER BY AUTDJ_D_SOLICITUD DESC',
                    $params
                );
            }
        } catch (\Throwable $e) {
            $this->mensaje = 'Error: ' . $e->getMessage();
            $this->tipoMensaje = 'danger';
        }

        return [
            'pendientes' => $pendientes,
            'reporte' => $reporte,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Autorizar / Rechazar (estado 1 = autorizada, 2 = rechazada)
    |--------------------------------------------------------------------------
    */

    public function responder(int $codigo, string $accion)
    {
        if (! in_array($accion, ['autorizar', 'rechazar'], true)) {
            return;
        }

        try {
            // Solo afecta al registro seleccionado y únicamente si sigue pendiente
            $filas = DB::update(
                'UPDATE dbo.TG_AUTORIZACION_DJ
                 SET AUTDJ_N_ESTADO = ?, AUTDJ_D_RESPUESTA = GETDATE(), USUARI_N_CODAUT = ?
                 WHERE AUTDJ_N_CODIGO = ? AND AUTDJ_N_ESTADO = 0',
                [
                    $accion === 'autorizar' ? 1 : 2,
                    auth()->user()->USUARI_N_CODIGO,
                    $codigo,
                ]
            );

            if ($filas === 0) {
                throw new \RuntimeException('La solicitud ya fue respondida o no existe');
            }

            $this->mensaje = 'Solicitud #' . $codigo . ' ' . ($accion === 'autorizar' ? 'AUTORIZADA' : 'RECHAZADA');
            $this->tipoMensaje = 'success';
        } catch (\Throwable $e) {
            $this->mensaje = 'Error: ' . $e->getMessage();
            $this->tipoMensaje = 'danger';
        }
    }


    public function consultarReporte()
    {
        $this->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'estado' => 'nullable|in:0,1,2',
        ]);

        $this->verReporte = true;
    }
};
?>

<div
    class="space-y-8 p-6"
    @if (config('panel.actualizacion') === 'polling') wire:poll.{{ config('panel.poll_segundos') }}s @endif
>

    <div class="flex items-end justify-between gap-4">

        <div>
            <flux:heading size="xl">Declaraciones Juradas</flux:heading>

            <flux:text>Control de autorizaciones de declaraciones juradas</flux:text>
        </div>

        <flux:button wire:click="$refresh" icon="arrow-path">
            Recargar
        </flux:button>

    </div>

    @if ($mensaje)
        <flux:callout
            :variant="$tipoMensaje"
            :text="$mensaje"
        />
    @endif


    {{-- =========================================================
         AUTORIZACIONES PENDIENTES
    ========================================================== --}}

    <section>

        <flux:heading size="lg" class="mb-3">Autorizaciones pendientes</flux:heading>

        <div class="overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700">

            <table class="w-full text-sm">

                <thead class="bg-zinc-100 text-left dark:bg-zinc-800">
                    <tr>
                        <th class="px-3 py-2">N°</th>
                        <th class="px-3 py-2">Usuario</th>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Equipo</th>
                        <th class="px-3 py-2">IP</th>
                        <th class="px-3 py-2">Contribuyente</th>
                        <th class="px-3 py-2">Solicitud</th>
                        <th class="px-3 py-2">Espera (seg)</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($pendientes as $p)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="dj-{{ $p->AUTDJ_N_CODIGO }}">
                            <td class="px-3 py-2">{{ $p->AUTDJ_N_CODIGO }}</td>
                            <td class="px-3 py-2">{{ $p->USUARIO }}</td>
                            <td class="px-3 py-2">{{ $p->USUARIO_NOMBRE }}</td>
                            <td class="px-3 py-2">{{ $p->HOSTNAME }}</td>
                            <td class="px-3 py-2">{{ $p->IP }}</td>
                            <td class="px-3 py-2">{{ $p->CONTRI_N_CONSIS }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $p->AUTDJ_D_SOLICITUD }}</td>
                            <td class="px-3 py-2">{{ $p->SEGUNDOS_ESPERA }}</td>
                            <td class="flex justify-end gap-2 px-3 py-2">
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="responder({{ $p->AUTDJ_N_CODIGO }}, 'autorizar')"
                                    wire:confirm="¿Autorizar la solicitud #{{ $p->AUTDJ_N_CODIGO }} de {{ $p->USUARIO }}?"
                                >
                                    Autorizar
                                </flux:button>

                                <flux:button
                                    size="sm"
                                    variant="danger"
                                    wire:click="responder({{ $p->AUTDJ_N_CODIGO }}, 'rechazar')"
                                    wire:confirm="¿Rechazar la solicitud #{{ $p->AUTDJ_N_CODIGO }} de {{ $p->USUARIO }}?"
                                >
                                    Rechazar
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-6 text-center text-zinc-500">
                                Sin autorizaciones pendientes
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>

        </div>

    </section>


    {{-- =========================================================
         REPORTE
    ========================================================== --}}

   {{--  <section>

        <flux:heading size="lg" class="mb-3">Reporte de autorizaciones</flux:heading>

        <div class="mb-3 flex flex-wrap items-end gap-3">

            <flux:input type="date" wire:model="desde" label="Desde" />

            <flux:input type="date" wire:model="hasta" label="Hasta" />

            <flux:select wire:model="estado" label="Estado" class="w-44">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="0">Pendientes</flux:select.option>
                <flux:select.option value="1">Autorizadas</flux:select.option>
                <flux:select.option value="2">Rechazadas</flux:select.option>
            </flux:select>

            <flux:button wire:click="consultarReporte" variant="primary" icon="magnifying-glass">
                Consultar
            </flux:button>

        </div>

        @if ($verReporte)

            <flux:text class="mb-2">{{ count($reporte) }} registro(s)</flux:text>

            <div class="max-h-[60vh] overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700">

                <table class="w-full text-sm">

                    <thead class="sticky top-0 bg-zinc-100 text-left dark:bg-zinc-800">
                        <tr>
                            <th class="px-3 py-2">N°</th>
                            <th class="px-3 py-2">Usuario</th>
                            <th class="px-3 py-2">Nombre</th>
                            <th class="px-3 py-2">Acción</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Solicitud</th>
                            <th class="px-3 py-2">Respuesta</th>
                            <th class="px-3 py-2">Espera (seg)</th>
                            <th class="px-3 py-2">Supervisor</th>
                            <th class="px-3 py-2">Contribuyente</th>
                            <th class="px-3 py-2">Equipo</th>
                            <th class="px-3 py-2">IP</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($reporte as $r)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="rep-{{ $r->AUTDJ_N_CODIGO }}">
                                <td class="px-3 py-2">{{ $r->AUTDJ_N_CODIGO }}</td>
                                <td class="px-3 py-2">{{ $r->USUARIO }}</td>
                                <td class="px-3 py-2">{{ $r->USUARIO_NOMBRE }}</td>
                                <td class="px-3 py-2">{{ $r->AUTDJ_T_ACCION }}</td>
                                <td class="px-3 py-2">{{ $r->ESTADO_DESC }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $r->AUTDJ_D_SOLICITUD }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $r->AUTDJ_D_RESPUESTA }}</td>
                                <td class="px-3 py-2">{{ $r->SEGUNDOS_ESPERA }}</td>
                                <td class="px-3 py-2">{{ $r->SUPERVISOR }}</td>
                                <td class="px-3 py-2">{{ $r->CONTRI_N_CONSIS }}</td>
                                <td class="px-3 py-2">{{ $r->HOSTNAME }}</td>
                                <td class="px-3 py-2">{{ $r->IP }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-3 py-6 text-center text-zinc-500">
                                    Sin registros
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>

            </div>

        @endif

    </section> --}}

</div>
