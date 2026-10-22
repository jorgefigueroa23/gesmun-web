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
    | Reporte de Declaraciones Juradas (0 pendiente, 1 autorizada, 2 rechazada, 3 cancelada)
    |--------------------------------------------------------------------------
    */

    public string $desde = '';

    public string $hasta = '';

    public string $usuario = '';

    public string $supervisor = '';

    public string $contribuyente = '';

    public string $estado = '';

    public int $porPagina = 10;

    // Al cambiar cualquier filtro se vuelve a la primera página
    public function updated()
    {
        $this->resetPage();
    }

    public function limpiar()
    {
        $this->reset(['desde', 'hasta', 'usuario', 'supervisor', 'contribuyente', 'estado']);

        $this->resetPage();
    }

    public function with()
    {
        $this->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'estado' => 'nullable|in:0,1,2,3',
            'porPagina' => 'in:10,20,50,100',
        ]);

        $where = ['1 = 1'];
        $params = [];

        if ($this->estado !== '') {
            $where[] = 'AUTDJ_N_ESTADO = ?';
            $params[] = (int) $this->estado;
        }

        if ($this->desde !== '') {
            $where[] = 'AUTDJ_D_SOLICITUD >= ?';
            $params[] = $this->desde;
        }

        if ($this->hasta !== '') {
            $where[] = 'AUTDJ_D_SOLICITUD < DATEADD(DAY, 1, CAST(? AS DATE))';
            $params[] = $this->hasta;
        }

        if ($this->usuario !== '') {
            $where[] = '(USUARIO LIKE ? OR USUARIO_NOMBRE LIKE ?)';
            $params[] = '%' . $this->usuario . '%';
            $params[] = '%' . $this->usuario . '%';
        }

        if ($this->supervisor !== '') {
            $where[] = '(SUPERVISOR LIKE ? OR SUPERVISOR_NOMBRE LIKE ?)';
            $params[] = '%' . $this->supervisor . '%';
            $params[] = '%' . $this->supervisor . '%';
        }

        if ($this->contribuyente !== '') {
            $where[] = 'CAST(CONTRI_N_CONSIS AS VARCHAR(30)) LIKE ?';
            $params[] = '%' . $this->contribuyente . '%';
        }

        $condicion = implode(' AND ', $where);

        $pagina = $this->getPage();

        try {
            $total = (int) DB::selectOne(
                "SELECT COUNT(*) AS total FROM dbo.VW_TG_AUTORIZACION_DJ_REPORTE WHERE {$condicion}",
                $params
            )->total;

            $filas = DB::select(
                "SELECT AUTDJ_N_CODIGO, USUARIO, USUARIO_NOMBRE, AUTDJ_T_ACCION, ESTADO_DESC, AUTDJ_N_ESTADO, AUTDJ_D_SOLICITUD,
                        AUTDJ_D_RESPUESTA, SEGUNDOS_ESPERA, SUPERVISOR, SUPERVISOR_NOMBRE,
                        CONTRI_N_CONSIS, HOSTNAME, IP, AUTDJ_T_OBSERV
                 FROM dbo.VW_TG_AUTORIZACION_DJ_REPORTE
                 WHERE {$condicion}
                 ORDER BY AUTDJ_D_SOLICITUD DESC
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
            'total' => $total,
            'registros' => new LengthAwarePaginator(
                $filas,
                $total,
                $this->porPagina,
                $pagina,
                ['path' => request()->url()]
            ),
        ];
    }
};
?>

<div class="space-y-6 p-6">

    <div>
        <flux:heading size="xl">Reportes</flux:heading>

        <flux:text>Declaraciones juradas por estado</flux:text>
    </div>


    {{-- Filtros
         Tamaño personalizable (se aplica al recargar, no requiere recompilar):
           --filtro-ancho-min : ancho mínimo de cada campo (ej. 8rem, 150px)
           --filtro-ancho-max : ancho máximo de todo el bloque de filtros (ej. 40rem, 100%)
           --filtro-espacio   : separación entre campos
    --}}

    <div
        class="grid items-end"
        style="--filtro-ancho-min: 15rem; --filtro-ancho-max: 100%; --filtro-espacio: 0.75rem;
               max-width: var(--filtro-ancho-max);
               gap: var(--filtro-espacio);
               grid-template-columns: repeat(auto-fill, minmax(var(--filtro-ancho-min), 1fr));"
    >
        <flux:input type="date" wire:model.live="desde" label="Desde" />
        <flux:input type="date" wire:model.live="hasta" label="Hasta" />
        <flux:input wire:model.live.debounce.400ms="usuario" label="Usuario" placeholder="Código o nombre" icon="magnifying-glass" clearable />
        <flux:input wire:model.live.debounce.400ms="supervisor" label="Supervisor" placeholder="Código o nombre" icon="magnifying-glass" clearable />
        <flux:input wire:model.live.debounce.400ms="contribuyente" label="Contribuyente" placeholder="Código" icon="magnifying-glass" clearable />
        <flux:select wire:model.live="estado" label="Estado">
            <flux:select.option value="">Todos</flux:select.option>
            <flux:select.option value="0">Pendientes</flux:select.option>
            <flux:select.option value="1">Autorizadas</flux:select.option>
            <flux:select.option value="2">Rechazadas</flux:select.option>
            <flux:select.option value="3">Canceladas</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="porPagina" label="Registros por página">
            <flux:select.option value="10">10</flux:select.option>
            <flux:select.option value="20">20</flux:select.option>
            <flux:select.option value="50">50</flux:select.option>
            <flux:select.option value="100">100</flux:select.option>
        </flux:select>
    </div>

    <div class="flex items-center justify-between">
        <flux:text>{{ number_format($total) }} DJ</flux:text>

        <flux:button wire:click="limpiar" icon="x-mark" size="sm">Limpiar filtros</flux:button>
    </div>

    @if ($error)
        <flux:callout variant="danger" :text="'Error: ' . $error" />
    @endif


    {{-- Tabla responsive: sin scroll, el contenido se ajusta en varias líneas --}}

    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">

        <table class="w-full table-fixed text-[11px] sm:text-xs lg:text-sm">

            <thead class="bg-zinc-100 text-left dark:bg-zinc-800">
                <tr>
                    <th class="w-[6%] px-1 py-1.5 sm:px-2 sm:py-2">N°</th>
                    <th class="w-[8%] px-1 py-1.5 sm:px-2 sm:py-2">Usuario</th>
                    <th class="w-[8%] px-1 py-1.5 sm:px-2 sm:py-2">Acción</th>
                    <th class="w-[8%] px-1 py-1.5 sm:px-2 sm:py-2">Estado</th>
                    <th class="w-[12%] px-1 py-1.5 sm:px-2 sm:py-2">Solicitud</th>
                    <th class="w-[12%] px-1 py-1.5 sm:px-2 sm:py-2">Respuesta</th>
                    <th class="w-[7%] px-1 py-1.5 sm:px-2 sm:py-2">Espera (seg)</th>
                    <th class="w-[14%] px-1 py-1.5 sm:px-2 sm:py-2">Supervisor</th>
                    <th class="w-[9%] px-1 py-1.5 sm:px-2 sm:py-2">Contribuyente</th>
                    <th class="w-[10%] px-1 py-1.5 sm:px-2 sm:py-2">Equipo / IP</th>
                    <th class="w-[9%] px-1 py-1.5 sm:px-2 sm:py-2">Observación</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($registros as $r)
                    <tr class="border-t border-zinc-200 align-top dark:border-zinc-700" wire:key="rep-{{ $r->AUTDJ_N_CODIGO }}">
                        <td class="px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->AUTDJ_N_CODIGO }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->USUARIO }}<br><span class="text-zinc-500">{{ $r->USUARIO_NOMBRE }}</span></td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->AUTDJ_T_ACCION }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->ESTADO_DESC }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->AUTDJ_D_SOLICITUD }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->AUTDJ_D_RESPUESTA }}</td>
                        <td class="px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->SEGUNDOS_ESPERA }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->SUPERVISOR }}<br><span class="text-zinc-500">{{ $r->SUPERVISOR_NOMBRE }}</span></td>
                        <td class="break-all px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->CONTRI_N_CONSIS }}</td>
                        <td class="break-all px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->HOSTNAME }}<br>{{ $r->IP }}</td>
                        <td class="break-words px-1 py-1.5 sm:px-2 sm:py-2">{{ $r->AUTDJ_T_OBSERV }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-3 py-6 text-center text-zinc-500">Sin registros</td>
                    </tr>
                @endforelse
            </tbody>

        </table>

    </div>

    {{ $registros->links() }}

</div>
