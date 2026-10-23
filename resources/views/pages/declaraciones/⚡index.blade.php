<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /*
    |--------------------------------------------------------------------------
    | Control de Declaraciones Juradas
    |--------------------------------------------------------------------------
    |
    | Una sola lista: las solicitudes pendientes (con botones para autorizar o
    | rechazar) y las que respondió el usuario en sesión.
    |
    | Estados: 0 pendiente, 1 autorizada, 2 rechazada.
    |
    */

    public string $buscar = '';

    public int $porPagina = 20;


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

    public function updatedBuscar()
    {
        $this->resetPage();
    }


    public function with()
    {
        // Pendientes de cualquiera + las respondidas por el usuario en sesión
        $where = ['(AUTDJ_N_ESTADO = 0 OR (AUTDJ_N_ESTADO IN (1, 2) AND USUARI_N_CODAUT = ?))'];
        $params = [auth()->user()->USUARI_N_CODIGO];

        $buscar = trim($this->buscar);

        if ($buscar !== '') {
            $where[] = '(USUARIO LIKE ? OR USUARIO_NOMBRE LIKE ? OR CAST(CONTRI_N_CONSIS AS VARCHAR(30)) LIKE ?)';
            array_push($params, "%{$buscar}%", "%{$buscar}%", "%{$buscar}%");
        }

        $condicion = implode(' AND ', $where);
        $pagina = $this->getPage();

        try {
            $total = (int) DB::selectOne(
                "SELECT COUNT(*) AS total FROM dbo.VW_TG_AUTORIZACION_DJ_REPORTE WHERE {$condicion}",
                $params
            )->total;

            // Primero las pendientes (más antiguas arriba), luego las respondidas (más recientes arriba)
            $filas = DB::select(
                "SELECT AUTDJ_N_CODIGO, USUARIO, USUARIO_NOMBRE, AUTDJ_N_ESTADO, ESTADO_DESC,
                        HOSTNAME, IP, CONTRI_N_CONSIS, AUTDJ_D_SOLICITUD, AUTDJ_D_RESPUESTA, SEGUNDOS_ESPERA
                 FROM dbo.VW_TG_AUTORIZACION_DJ_REPORTE
                 WHERE {$condicion}
                 ORDER BY CASE WHEN AUTDJ_N_ESTADO = 0 THEN 0 ELSE 1 END,
                          CASE WHEN AUTDJ_N_ESTADO = 0 THEN AUTDJ_D_SOLICITUD END ASC,
                          AUTDJ_D_RESPUESTA DESC
                 OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
                array_merge($params, [($pagina - 1) * $this->porPagina, $this->porPagina])
            );

            $error = null;
        } catch (\Throwable $e) {
            $total = 0;
            $filas = [];
            $error = $e->getMessage();
        }

        return [
            'error' => $error,
            'declaraciones' => new LengthAwarePaginator(
                $filas,
                $total,
                $this->porPagina,
                $pagina,
                ['path' => request()->url()]
            ),
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

            $this->dispatch(
                'swal',
                icon: 'success',
                title: 'Solicitud #' . $codigo . ($accion === 'autorizar' ? ' autorizada' : ' rechazada'),
            );
        } catch (\Throwable $e) {
            $this->dispatch('swal', icon: 'error', title: 'No se pudo completar la acción', text: $e->getMessage());
        }
    }
};
?>

<div
    class="p-6"
    @if (config('panel.actualizacion') === 'polling') wire:poll.{{ config('panel.poll_segundos') }}s @endif
>

    {{-- =========================================================
         ENCABEZADO
    ========================================================== --}}

    <div class="mb-6 flex items-end justify-between gap-4">

        <div>
            <flux:heading size="xl">Declaraciones Juradas</flux:heading>

            <flux:text>Solicitudes pendientes y las que autorizaste o rechazaste</flux:text>
        </div>

        {{-- Buscador --}}

        <div class="w-72">
            <flux:input
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar usuario o contribuyente..."
                icon="magnifying-glass"
                clearable
            />
        </div>

    </div>

    @if ($error)
        <flux:callout variant="danger" :text="'Error: ' . $error" class="mb-4" />
    @endif


    {{-- =========================================================
         TABLA
    ========================================================== --}}

    <div class="relative">

        <div
            wire:loading
            wire:target="buscar, previousPage, nextPage, gotoPage, responder"
            class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 dark:bg-zinc-900/60"
        >
            <div class="flex items-center gap-2 rounded-lg bg-white px-4 py-3 shadow dark:bg-zinc-800">
                <flux:icon name="arrow-path" class="size-5 animate-spin" />
                <span class="text-sm">Cargando...</span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">

            <table class="w-full text-sm">

                <thead class="bg-zinc-50 text-left dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3">N°</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Equipo / IP</th>
                        <th class="px-4 py-3">Contribuyente</th>
                        <th class="px-4 py-3">Solicitud</th>
                        <th class="px-4 py-3">Respuesta</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($declaraciones as $d)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="dj-{{ $d->AUTDJ_N_CODIGO }}">
                            <td class="px-4 py-3">{{ $d->AUTDJ_N_CODIGO }}</td>
                            <td class="px-4 py-3">{{ $d->USUARIO }}</td>
                            <td class="px-4 py-3">{{ $d->USUARIO_NOMBRE }}</td>
                            <td class="px-4 py-3">{{ $d->HOSTNAME }}<br><span class="text-zinc-500">{{ $d->IP }}</span></td>
                            <td class="px-4 py-3">{{ $d->CONTRI_N_CONSIS }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $d->AUTDJ_D_SOLICITUD }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $d->AUTDJ_D_RESPUESTA }}</td>

                            <td class="px-4 py-3">
                                @if ($d->AUTDJ_N_ESTADO == 0)
                                    <flux:badge size="sm" color="amber">Pendiente</flux:badge>
                                @elseif ($d->AUTDJ_N_ESTADO == 1)
                                    <flux:badge size="sm" color="green">Autorizada</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red">Rechazada</flux:badge>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if ($d->AUTDJ_N_ESTADO == 0)
                                    <div class="flex gap-2">
                                        <flux:button
                                            size="sm"
                                            variant="primary"
                                            x-data
                                            x-on:click="confirmarSwal({{ Js::from([
                                                'title' => '¿Autorizar la solicitud #' . $d->AUTDJ_N_CODIGO . '?',
                                                'text' => 'Se permitirá a ' . $d->USUARIO . ' (' . $d->USUARIO_NOMBRE . ').',
                                                'icon' => 'question',
                                                'confirmText' => 'Sí, autorizar',
                                                'confirmColor' => '#16a34a',
                                            ]) }}).then(ok => ok && $wire.responder({{ $d->AUTDJ_N_CODIGO }}, 'autorizar'))"
                                        >
                                            Autorizar
                                        </flux:button>

                                        <flux:button
                                            size="sm"
                                            variant="danger"
                                            x-data
                                            x-on:click="confirmarSwal({{ Js::from([
                                                'title' => '¿Rechazar la solicitud #' . $d->AUTDJ_N_CODIGO . '?',
                                                'text' => 'Se denegará a ' . $d->USUARIO . ' (' . $d->USUARIO_NOMBRE . ').',
                                                'icon' => 'warning',
                                                'confirmText' => 'Sí, rechazar',
                                                'confirmColor' => '#dc2626',
                                            ]) }}).then(ok => ok && $wire.responder({{ $d->AUTDJ_N_CODIGO }}, 'rechazar'))"
                                        >
                                            Rechazar
                                        </flux:button>
                                    </div>
                                @else
                                    <flux:button size="sm" variant="ghost" disabled>
                                        Respondida
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center">
                                No se encontraron declaraciones.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>

        </div>

    </div>


    {{-- =========================================================
         PAGINACIÓN
    ========================================================== --}}

    <div class="mt-6">
        {{ $declaraciones->links() }}
    </div>

</div>
