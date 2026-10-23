<?php

use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    /*
    |--------------------------------------------------------------------------
    | Control de Sesiones
    |--------------------------------------------------------------------------
    |
    | Lógica tomada de example-app (TestController::sesiones / desconectar).
    |
    */

    public string $mensaje = '';

    public string $tipoMensaje = 'success';


    // Tiempo real: Reverb avisa cuando cambian los datos y se vuelve a consultar
    protected function getListeners()
    {
        return [
            'echo-private:panel,.SesionesActualizadas' => '$refresh',
        ];
    }


    public function with()
    {
        try {
            $sesiones = DB::select(<<<'SQL'
                SELECT SESSION_ID,
                       USUARI_N_CODIGO,
                       WINDOWS_USER,
                       HOSTNAME,
                       IP,
                       MAC,
                       FECHA_INICIO,
                       ULTIMA_ACTIVIDAD,
                       DATEDIFF(MINUTE, FECHA_INICIO, SYSDATETIME()) AS MINUTOS_CONECTADO
                FROM dbo.TG_USUARIO_SESION
                WHERE FECHA_CIERRE IS NULL
                ORDER BY FECHA_INICIO DESC
            SQL);
        } catch (\Throwable $e) {
            $sesiones = [];
            $this->mensaje = 'Error: ' . $e->getMessage();
            $this->tipoMensaje = 'danger';
        }

        return [
            'sesiones' => $sesiones,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Cierra las sesiones abiertas del usuario
    |--------------------------------------------------------------------------
    */

    public function desconectar(int $codigoUsuario)
    {
        try {
            $cerradas = DB::update(
                'UPDATE dbo.TG_USUARIO_SESION
                 SET FECHA_CIERRE = SYSDATETIME()
                 WHERE USUARI_N_CODIGO = ? AND FECHA_CIERRE IS NULL',
                [$codigoUsuario]
            );

            $this->dispatch('swal', icon: 'success', title: "Sesiones cerradas: {$cerradas}");
        } catch (\Throwable $e) {
            $this->dispatch('swal', icon: 'error', title: 'No se pudo desconectar', text: $e->getMessage());
        }
    }
};
?>

<div class="p-6">

    <div class="mb-6 flex items-end justify-between gap-4">

        <div>
            <flux:heading size="xl">Control de Sesiones</flux:heading>

            <flux:text>Sesiones activas de los usuarios del sistema</flux:text>
        </div>

        <flux:button wire:click="$refresh" icon="arrow-path">
            Recargar
        </flux:button>

    </div>

    @if ($mensaje)
        <flux:callout
            :variant="$tipoMensaje"
            :text="$mensaje"
            class="mb-4"
        />
    @endif

    {{-- Tabla responsive: sin scroll, el contenido se ajusta en varias líneas --}}

    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">

        <table class="w-full table-fixed text-[11px] sm:text-xs lg:text-sm">

            <thead class="bg-zinc-100 text-left dark:bg-zinc-800">
                <tr>
                    <th class="w-[5%] px-1 py-1.5 sm:px-2 sm:py-2">Usuario</th>
                    <th class="w-[13%] px-1 py-1.5 sm:px-2 sm:py-2">Usuario Windows</th>
                    <th class="w-[13%] px-1 py-1.5 sm:px-2 sm:py-2">Equipo</th>
                    <th class="w-[5%] lg:w-[11%] px-1 py-1.5 sm:px-2 sm:py-2">IP</th>
                    <th class="w-[16%] px-1 py-1.5 sm:px-2 sm:py-2">MAC</th>
                    <th class="w-[14%] px-1 py-1.5 sm:px-2 sm:py-2">Inicio</th>
                    <th class="w-[14%] px-1 py-1.5 sm:px-2 sm:py-2">Última actividad</th>
                    <th class="w-[7%] px-1 py-1.5 sm:px-2 sm:py-2">Min.</th>
                    <th class="w-[8%] lg:w-[11%] px-1 py-1.5 sm:px-2 sm:py-2"></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($sesiones as $s)
                    <tr class="border-t border-zinc-200 align-top dark:border-zinc-700" wire:key="ses-{{ $s->SESSION_ID }}">
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->USUARI_N_CODIGO }}</td>
                        <td class="break-all px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->WINDOWS_USER }}</td>
                        <td class="break-all px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->HOSTNAME }}</td>
                        <td class="break-all px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->IP }}</td>
                        <td class="break-all px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->MAC }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->FECHA_INICIO }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->ULTIMA_ACTIVIDAD }}</td>
                        <td class="px-1 py-1.5 sm:px-2 sm:py-2">{{ $s->MINUTOS_CONECTADO }}</td>
                        <td class="px-1 py-1.5 sm:px-2 sm:py-2 text-right">
                            <flux:button
                                size="xs"
                                title="Desconectar"
                                variant="danger"
                                x-data
                                x-on:click="confirmarSwal({{ Js::from([
                                    'title' => '¿Desconectar al usuario ' . $s->USUARI_N_CODIGO . '?',
                                    'text' => 'Se cerrarán todas sus sesiones abiertas.',
                                    'icon' => 'warning',
                                    'confirmText' => 'Sí, desconectar',
                                    'confirmColor' => '#dc2626',
                                ]) }}).then(ok => ok && $wire.desconectar({{ $s->USUARI_N_CODIGO }}))"
                            >
                                <span class="hidden lg:inline">Desconectar</span>
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-6 text-center text-zinc-500">
                            Sin sesiones activas
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

</div>
