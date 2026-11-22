<?php

namespace App\Enums\Cafeteria;

/** Estado de una cafetería. Se crea en BORRADOR; CERRADA es final. */
enum EstadoCafeteria: string
{
    case BORRADOR = 'BORRADOR';
    case ACTIVA = 'ACTIVA';
    case SUSPENDIDA = 'SUSPENDIDA';
    case CERRADA = 'CERRADA';

    /** @return list<self> estados a los que se puede pasar desde este. */
    public function siguientes(): array
    {
        return match ($this) {
            self::BORRADOR => [self::ACTIVA, self::CERRADA],
            self::ACTIVA => [self::SUSPENDIDA, self::CERRADA],
            self::SUSPENDIDA => [self::ACTIVA, self::CERRADA],
            self::CERRADA => [],
        };
    }
}
