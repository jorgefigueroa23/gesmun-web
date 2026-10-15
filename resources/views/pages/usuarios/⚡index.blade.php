<?php

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /*
    |--------------------------------------------------------------------------
    | Búsqueda
    |--------------------------------------------------------------------------
    */

    public string $buscar = '';


    /*
    |--------------------------------------------------------------------------
    | Modal de desactivación
    |--------------------------------------------------------------------------
    */

    public bool $mostrarModalDesactivar = false;

    public ?int $usuarioCodigo = null;

    public string $nombreUsuario = '';

    public string $codigoUsuario = '';

    public string $claveUsuario = '';

    public string $fechaBaja = '';

    public string $observacion = '';

    public ?int $perfilUsuario = null;

    public string $usuarioModifica = '';


    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    */

    public function updatedBuscar()
    {
        $this->resetPage();
    }


    /*
    |--------------------------------------------------------------------------
    | Determinar si el usuario está desactivado
    |--------------------------------------------------------------------------
    |
    | USUARI_T_FECBAJ se almacena normalmente como:
    |
    | 20260831
    |
    | Si la fecha es igual o anterior a hoy:
    |
    | Desactivado
    |
    */

    public function usuarioEstaDesactivado($fechaBaja): bool
    {
        if (empty($fechaBaja)) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Convertimos el valor recibido a string
        |--------------------------------------------------------------------------
        */

        $fechaBaja = trim((string) $fechaBaja);


        /*
        |--------------------------------------------------------------------------
        | Normalizamos la fecha.
        |
        | Si viene como:
        |
        | 20260831
        |
        | la dejamos igual.
        |
        | Si eventualmente SQL Server devuelve:
        |
        | 2026-08-31
        |
        | también lo convertimos.
        |--------------------------------------------------------------------------
        */

        try {

            if (preg_match('/^\d{8}$/', $fechaBaja)) {

                $fecha = \Carbon\Carbon::createFromFormat(
                    'Ymd',
                    $fechaBaja
                );

            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaBaja)) {

                $fecha = \Carbon\Carbon::createFromFormat(
                    'Y-m-d',
                    $fechaBaja
                );

            } else {

                return false;
            }

        } catch (\Throwable $e) {

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Fecha de baja igual o anterior a hoy
        |--------------------------------------------------------------------------
        */

        return $fecha->startOfDay()->lessThanOrEqualTo(
            now()->startOfDay()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Listado de usuarios
    |--------------------------------------------------------------------------
    */

    public function with()
    {
        return [
            'usuarios' => Usuario::query()

                ->when($this->buscar !== '', function ($query) {

                    $buscar = '%' . $this->buscar . '%';

                    $query->where(function ($query) use ($buscar) {

                        $query->where(
                            'USUARI_T_CODUSU',
                            'like',
                            $buscar
                        )

                        ->orWhere(
                            'USUARI_T_NOMUSU',
                            'like',
                            $buscar
                        )

                        ->orWhere(
                            'USUARI_N_CODIGO',
                            'like',
                            $buscar
                        );
                    });
                })

                ->orderBy('USUARI_N_CODIGO')

                ->paginate(20),
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Abrir modal de desactivación
    |--------------------------------------------------------------------------
    */

    public function abrirModalDesactivar($codigo)
    {
        $usuario = Usuario::find($codigo);

        if (!$usuario) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Datos del usuario
        |--------------------------------------------------------------------------
        */

        $this->usuarioCodigo =
            $usuario->USUARI_N_CODIGO;

        $this->nombreUsuario =
            $usuario->USUARI_T_NOMUSU ?? '';

        $this->codigoUsuario =
            $usuario->USUARI_T_CODUSU ?? '';


        /*
        |--------------------------------------------------------------------------
        | Conservamos la contraseña porque el procedimiento
        | almacenado la solicita.
        |--------------------------------------------------------------------------
        */

        $this->claveUsuario =
            $usuario->USUARI_T_CLAUSU ?? '';


        /*
        |--------------------------------------------------------------------------
        | Fecha actual
        |
        | Se utiliza Y-m-d porque es el formato que entiende
        | correctamente el input.
        |--------------------------------------------------------------------------
        */

        $this->fechaBaja =
            now()->format('Y-m-d');


        /*
        |--------------------------------------------------------------------------
        | Observación
        |--------------------------------------------------------------------------
        */

        $this->observacion =
            $usuario->USUARI_T_OBSERV ?? '';


        /*
        |--------------------------------------------------------------------------
        | Perfil
        |--------------------------------------------------------------------------
        */

        $this->perfilUsuario =
            $usuario->PERUSU_N_CODIGO;


        /*
        |--------------------------------------------------------------------------
        | Usuario que realiza la modificación
        |--------------------------------------------------------------------------
        */

        $this->usuarioModifica =
            auth()->user()->USUARI_T_CODUSU ?? '';


        /*
        |--------------------------------------------------------------------------
        | Mostramos modal
        |--------------------------------------------------------------------------
        */

        $this->mostrarModalDesactivar = true;
    }


    /*
    |--------------------------------------------------------------------------
    | Primera confirmación
    |--------------------------------------------------------------------------
    */

    public function desactivar()
    {
        /*
        |--------------------------------------------------------------------------
        | Cerramos el modal Flux antes de mostrar SweetAlert
        |--------------------------------------------------------------------------
        */

        $this->mostrarModalDesactivar = false;


        /*
        |--------------------------------------------------------------------------
        | Enviamos evento a JavaScript
        |--------------------------------------------------------------------------
        */

        $this->dispatch('confirmar-desactivacion');
    }


    /*
    |--------------------------------------------------------------------------
    | Ejecutar procedimiento almacenado
    |--------------------------------------------------------------------------
    */

    public function confirmarDesactivacion()
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | Convertir fecha:
            |
            | 2026-08-31
            |
            | a:
            |
            | 20260831
            |--------------------------------------------------------------------------
            */

            $fechaBaja = date(
                'Ymd',
                strtotime($this->fechaBaja)
            );


            /*
            |--------------------------------------------------------------------------
            | Ejecutamos el procedimiento almacenado.
            |
            | NO modificamos el procedimiento.
            |--------------------------------------------------------------------------
            */

            $resultado = DB::selectOne(
                'SET NOCOUNT ON;

                 EXEC PROC_TG_USUARIO_ACTUALIZAR

                    @USUARI_N_CODIGO = ?,

                    @USUARI_T_NOMUSU = ?,

                    @USUARI_T_CODUSU = ?,

                    @USUARI_T_CLAUSU = ?,

                    @USUARI_T_FECBAJ = ?,

                    @USUARI_T_OBSERV = ?,

                    @PERUSU_N_CODIGO = ?',

                [
                    $this->usuarioCodigo,
                    $this->nombreUsuario,
                    $this->codigoUsuario,
                    $this->claveUsuario,
                    $fechaBaja,
                    $this->observacion,
                    $this->perfilUsuario,
                ]
            );


            /*
            |--------------------------------------------------------------------------
            | Determinamos si el procedimiento tuvo éxito.
            |
            | 1 = éxito
            | 0 = error
            |--------------------------------------------------------------------------
            */

            $exito = false;


            /*
            |--------------------------------------------------------------------------
            | Primer caso:
            | SQL Server devuelve columna "computed"
            |--------------------------------------------------------------------------
            */

            if (
                $resultado &&
                isset($resultado->computed) &&
                (int) $resultado->computed === 1
            ) {
                $exito = true;
            }


            /*
            |--------------------------------------------------------------------------
            | Segundo caso:
            | SQL Server devuelve otro nombre de columna.
            |--------------------------------------------------------------------------
            */

            if (!$exito && $resultado) {

                $datosResultado = (array) $resultado;

                $valor = (int) reset($datosResultado);

                if ($valor === 1) {
                    $exito = true;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Si el procedimiento fue exitoso
            |--------------------------------------------------------------------------
            */

            if ($exito) {

                /*
                |--------------------------------------------------------------------------
                | Usuario actualmente autenticado
                |--------------------------------------------------------------------------
                */

                $usuarioLogueado = auth()->user();


                /*
                |--------------------------------------------------------------------------
                | Usuario que realizó la desactivación
                |--------------------------------------------------------------------------
                */

                $usuarioModifica =
                    $usuarioLogueado->USUARI_T_CODUSU;


                /*
                |--------------------------------------------------------------------------
                | Guardamos quién realizó la desactivación.
                |
                | NO modificamos USUARI_T_WEB.
                |--------------------------------------------------------------------------
                */

                // Versión anterior:
                // Usuario::where(
                //     'USUARI_N_CODIGO',
                //     $this->usuarioCodigo
                // )->update([
                //     'USUARI_T_WEB_MODIF' => $usuarioModifica,
                // ]);

                if (\Illuminate\Support\Facades\Schema::hasColumn((new Usuario)->getTable(), 'USUARI_T_WEB_MODIF')) {
                    Usuario::where(
                        'USUARI_N_CODIGO',
                        $this->usuarioCodigo
                    )->update([
                        'USUARI_T_WEB_MODIF' => $usuarioModifica,
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Enviamos mensaje de éxito.
                |--------------------------------------------------------------------------
                */

                $this->dispatch('desactivacion-exitosa');

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | El procedimiento devolvió 0
            |--------------------------------------------------------------------------
            */

            $this->dispatch('desactivacion-error');

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Guardamos el error en el log.
            |--------------------------------------------------------------------------
            */

            report($e);


            /*
            |--------------------------------------------------------------------------
            | Mostramos alerta de error.
            |--------------------------------------------------------------------------
            */

            $this->dispatch('desactivacion-error');
        }
    }
};

?>

<div class="p-6">

    {{-- =========================================================
         ENCABEZADO
    ========================================================== --}}

    <div class="mb-6 flex items-end justify-between gap-4">

        <div>

            <flux:heading size="xl">
                Usuarios
            </flux:heading>

            <flux:text>
                Administración de usuarios del sistema
            </flux:text>

        </div>


        {{-- Buscador --}}

        <div class="w-72">

            <flux:input
                wire:model.live.debounce.300ms="buscar"
                placeholder="Buscar usuario..."
                icon="magnifying-glass"
                clearable
            />

        </div>

    </div>


    {{-- =========================================================
         TABLA
    ========================================================== --}}

    <div class="relative">


        {{-- =====================================================
             LOADER
        ====================================================== --}}

        <div
            wire:loading
            wire:target="buscar, previousPage, nextPage, gotoPage, $refresh"
            class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 dark:bg-zinc-900/60"
        >

            <div class="flex items-center gap-2 rounded-lg bg-white px-4 py-3 shadow dark:bg-zinc-800">

                <flux:icon
                    name="arrow-path"
                    class="size-5 animate-spin"
                />

                <span class="text-sm">
                    Cargando...
                </span>

            </div>

        </div>


        {{-- =====================================================
             TABLA
        ====================================================== --}}

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">

            <table class="w-full text-sm">

                <thead class="bg-zinc-50 dark:bg-zinc-800">

                    <tr>

                        <th class="px-4 py-3 text-left">
                            Código
                        </th>

                        <th class="px-4 py-3 text-left">
                            Usuario de ingreso
                        </th>

                        <th class="px-4 py-3 text-left">
                            Usuario
                        </th>

                        <th class="px-4 py-3 text-left">
                            Contraseña
                        </th>

                        <th class="px-4 py-3 text-left">
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse ($usuarios as $usuario)

                        <tr
                            wire:key="usuario-{{ $usuario->USUARI_N_CODIGO }}"
                            class="border-t border-zinc-200 dark:border-zinc-700"
                        >

                            {{-- Código --}}

                            <td class="px-4 py-3">
                                {{ $usuario->USUARI_N_CODIGO }}
                            </td>


                            {{-- Usuario de ingreso --}}

                            <td class="px-4 py-3">
                                {{ $usuario->USUARI_T_CODUSU }}
                            </td>


                            {{-- Usuario --}}

                            <td class="px-4 py-3">
                                {{ $usuario->USUARI_T_NOMUSU }}
                            </td>


                            {{-- Contraseña --}}

                            <td class="px-4 py-3">
                                ********
                            </td>


                            {{-- Acciones --}}

                            <td class="px-4 py-3">

                                @if ($this->usuarioEstaDesactivado($usuario->USUARI_T_FECBAJ))

                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        disabled
                                    >
                                        Desactivado
                                    </flux:button>

                                @else

                                    <flux:button
                                        size="sm"
                                        variant="danger"
                                        wire:click="abrirModalDesactivar({{ $usuario->USUARI_N_CODIGO }})"
                                        wire:loading.attr="disabled"
                                        wire:target="abrirModalDesactivar({{ $usuario->USUARI_N_CODIGO }})"
                                    >
                                        Desactivar
                                    </flux:button>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="5"
                                class="px-4 py-6 text-center"
                            >
                                No se encontraron usuarios.
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

        {{ $usuarios->links() }}

    </div>


    {{-- =========================================================
         MODAL DESACTIVAR
    ========================================================== --}}

    <flux:modal
        name="modal-desactivar"
        wire:model="mostrarModalDesactivar"
        class="md:w-[500px]"
    >

        <div class="space-y-6">


            {{-- Título --}}

            <div>

                <flux:heading size="lg">
                    Desactivar usuario
                </flux:heading>

                <flux:text class="mt-2">
                    Verifique los datos antes de continuar.
                </flux:text>

            </div>


            {{-- Código y usuario de ingreso --}}

            <div class="grid grid-cols-2 gap-4">

                <flux:input
                    label="Código"
                    wire:model="usuarioCodigo"
                    disabled
                />

                <flux:input
                    label="Usuario de ingreso"
                    wire:model="codigoUsuario"
                    disabled
                />

            </div>


            {{-- Nombre --}}

            <flux:input
                label="Usuario"
                wire:model="nombreUsuario"
                disabled
            />


            {{-- Fecha de baja --}}

            <flux:input
                label="Fecha de baja"
                type="text"
                value="{{ \Carbon\Carbon::parse($fechaBaja)->format('d/m/Y') }}"
                disabled
            />


            {{-- Observación --}}

            <flux:textarea
                label="Observación"
                wire:model="observacion"
                placeholder="Ingrese una observación..."
            />


            {{-- Botones --}}

            <div class="flex justify-end gap-2">

                <flux:modal.close>

                    <flux:button variant="ghost">
                        Cancelar
                    </flux:button>

                </flux:modal.close>


                <flux:button
                    variant="danger"
                    wire:click="desactivar"
                    wire:loading.attr="disabled"
                    wire:target="desactivar"
                >
                    Aceptar
                </flux:button>

            </div>

        </div>

    </flux:modal>

</div>


{{-- =============================================================
     SWEETALERT
============================================================== --}}

@script

<script>

    /*
    |--------------------------------------------------------------------------
    | Confirmación antes de ejecutar el procedimiento
    |--------------------------------------------------------------------------
    */

    $wire.on('confirmar-desactivacion', () => {

        setTimeout(() => {

            Swal.fire({

                title: '¿Desactivar usuario ' + $wire.codigoUsuario + '?',

                text: 'El usuario será dado de baja.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Sí, desactivar',

                cancelButtonText: 'Cancelar',

                reverseButtons: true,

                allowOutsideClick: false,

            }).then((result) => {

                if (result.isConfirmed) {

                    $wire.confirmarDesactivacion();

                }

            });

        }, 250);

    });


    /*
    |--------------------------------------------------------------------------
    | Desactivación exitosa
    |--------------------------------------------------------------------------
    */

    $wire.on('desactivacion-exitosa', () => {

        Swal.fire({

            icon: 'success',

            title: 'Usuario desactivado correctamente',

            toast: true,

            position: 'top-end',

            showConfirmButton: false,

            timer: 2500,

            timerProgressBar: true,

        })
        // .then(() => {

        //     /*
        //     |--------------------------------------------------------------------------
        //     | Refrescamos el componente Livewire.
        //     |
        //     | Esto vuelve a ejecutar with() y consulta nuevamente
        //     | TG_USUARIO.
        //     |--------------------------------------------------------------------------
        //     */

        //     $wire.$refresh();

        // });

    });


    /*
    |--------------------------------------------------------------------------
    | Error
    |--------------------------------------------------------------------------
    */

    $wire.on('desactivacion-error', () => {

        Swal.fire({

            icon: 'error',

            title: 'No se pudo desactivar el usuario',

            toast: true,

            position: 'top-end',

            showConfirmButton: false,

            timer: 3000,

            timerProgressBar: true,

        });

    });

</script>

@endscript