import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Si el host configurado es "localhost" pero se entra desde otra PC, "localhost"
// apuntaría a esa PC y no al servidor, así que se usa el host de la barra de direcciones.
const hostConfigurado = import.meta.env.VITE_REVERB_HOST;
const wsHost = !hostConfigurado || ['localhost', '127.0.0.1'].includes(hostConfigurado)
    ? window.location.hostname
    : hostConfigurado;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
