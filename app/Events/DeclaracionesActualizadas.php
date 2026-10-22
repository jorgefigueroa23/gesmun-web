<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite cuando cambia la bandeja de autorizaciones DJ pendientes.
 */
class DeclaracionesActualizadas implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function broadcastOn(): array
    {
        return [new PrivateChannel('panel')];
    }

    public function broadcastAs(): string
    {
        return 'DeclaracionesActualizadas';
    }
}
