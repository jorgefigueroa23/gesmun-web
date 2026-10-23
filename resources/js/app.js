import Swal from 'sweetalert2';

window.Swal = Swal;
/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/*
|--------------------------------------------------------------------------
| SweetAlert: confirmaciones y avisos amigables
|--------------------------------------------------------------------------
|
| confirmarSwal({...}) devuelve una promesa que resuelve true si el usuario
| confirma. Los componentes Livewire emiten el evento "swal" para mostrar
| el resultado de una acción.
|
*/

window.confirmarSwal = async ({
    title,
    text = '',
    icon = 'question',
    confirmText = 'Aceptar',
    confirmColor = '#2563eb',
}) => {
    const result = await Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancelar',
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#71717a',
        reverseButtons: true,
        focusCancel: true,
        allowOutsideClick: false,
    });

    return result.isConfirmed;
};

window.addEventListener('swal', (e) => {
    const { icon = 'success', title = '', text = '' } = e.detail ?? {};

    Swal.fire({
        icon,
        title,
        text,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
});
