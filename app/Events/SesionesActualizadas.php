<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite cuando se abre o se cierra una sesión de usuario.
 */
class SesionesActualizadas implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function broadcastOn(): array
    {
        return [new PrivateChannel('panel')];
    }

    public function broadcastAs(): string
    {
        return 'SesionesActualizadas';
    }
}
