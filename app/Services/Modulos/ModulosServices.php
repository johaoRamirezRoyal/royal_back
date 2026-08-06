<?php

namespace App\Services\Modulos;

use App\Models\Modulos\ModuloVisita;
use App\Services\Service;
use Exception;

class ModulosServices extends Service
{
    public function registrarVisita(int $idUsuario, string $modulo): array
    {
        try {
            ModuloVisita::create([
                'id_usuario' => $idUsuario,
                'modulo' => $modulo,
            ]);

            return [
                'error' => false,
                'message' => 'Visita registrada',
                'data' => null,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al registrar la visita al módulo');
            return [
                'error' => true,
                'message' => 'Error en el servidor al registrar la visita al módulo',
                'data' => null,
            ];
        }
    }

    public function modulosMasVisitados(int $idUsuario, int $limite = 5): array
    {
        try {
            $mias = ModuloVisita::selectRaw('modulo, count(*) as total')
                ->where('id_usuario', $idUsuario)
                ->groupBy('modulo')
                ->orderByDesc('total')
                ->limit($limite)
                ->get();

            $generales = ModuloVisita::selectRaw('modulo, count(*) as total')
                ->groupBy('modulo')
                ->orderByDesc('total')
                ->limit($limite)
                ->get();

            return [
                'error' => false,
                'message' => 'Módulos más visitados obtenidos',
                'data' => [
                    'mias' => $mias,
                    'generales' => $generales,
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los módulos más visitados');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los módulos más visitados',
                'data' => ['mias' => [], 'generales' => []],
            ];
        }
    }
}
