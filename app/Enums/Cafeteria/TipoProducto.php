<?php

namespace App\Enums\Cafeteria;

/**
 * Clase de producto del catálogo. Las marcas `es_vendible` y `controla_inventario` van aparte;
 * `marcasSugeridas()` es solo el valor inicial del formulario.
 */
enum TipoProducto: string
{
    case REVENTA = 'REVENTA';             // se compra y se vende tal cual (gaseosa, paquete)
    case PREPARADO = 'PREPARADO';         // se prepara en la cafetería (almuerzo, jugo)
    case MATERIA_PRIMA = 'MATERIA_PRIMA'; // ingrediente de un preparado (harina, pollo)
    case INSUMO = 'INSUMO';               // se consume sin venderse (vasos, servilletas)

    /** @return array{es_vendible: bool, controla_inventario: bool} */
    public function marcasSugeridas(): array
    {
        return match ($this) {
            self::REVENTA => ['es_vendible' => true, 'controla_inventario' => true],
            self::PREPARADO => ['es_vendible' => true, 'controla_inventario' => false],
            self::MATERIA_PRIMA, self::INSUMO => ['es_vendible' => false, 'controla_inventario' => true],
        };
    }
}
