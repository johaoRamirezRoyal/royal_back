<?php

namespace App\Services\inventario;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioDescontinuado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\GenericMail;
use App\Models\Inventario\InventarioLiberado;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\NullsafeMethodCall;

class InventarioServices
{
    public function obtenerListadoInventario($perPage = 15, $search = null, $datos = [])
    {
        try {
            $listado = Inventario::select(
                'id_user',
                'id_area',
                'descripcion',
                'id_categoria',
                DB::raw("
                CONCAT(
                    '[',
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', inventario.id,
                            'marca', inventario.marca,
                            'modelo', inventario.modelo,
                            'precio', inventario.precio,
                            'estado_id', inventario.estado,
                            'estado_nombre', e.nombre,
                            'codigo', inventario.codigo
                        )
                    ),
                    ']'
                ) as items
            ")
            )
                ->leftJoin('estado as e', 'inventario.estado', '=', 'e.id')
                ->with([
                    'usuario:id_user,nombre,apellido',
                    'area:id,nombre',
                    'categoria:id,nombre'
                ])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('descripcion', 'like', "%{$search}%");
                    });
                })->when($datos['id_area'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('id_area', $datos['id_area']);
                })->when($datos['id_categoria'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('id_categoria', $datos['id_categoria']);
                })->when($datos['estado'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('estado', $datos['estado']);
                })->when($datos['id_usuario'] ?? null, function ($query) use ($datos) {
                    $query->where('id_user', $datos['id_usuario']);
                })
                ->groupBy('id_user', 'id_area', 'descripcion', 'id_categoria')
                ->paginate($perPage);

            // convertir string a JSON real
            $listado->transform(function ($item) {
                $item->items = json_decode($item->items);
                return $item;
            });

            return [
                'error' => false,
                'data' => $listado,
                'message' => "Listado de inventario obtenido"
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage()
            ];
        }
    }

    public function agregarInventario($inventario)
    {
        try {
            $inventario = Inventario::create($inventario);

            return [
                'error' => false,
                'data' => $inventario,
                'message' => "Inventario agregado"
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage()
            ];
        }
    }

    public function descontinuarInventario(array $ids, ?int $id_log = null)
    {
        try {

            $result = DB::transaction(function () use ($ids, $id_log) {

                $inventario = Inventario::whereIn('id', $ids)
                    ->where('estado', '!=', 5)
                    ->get();

                if ($inventario->isEmpty()) {
                    return [
                        "error" => true,
                        "data" => null,
                        "message" => "No se encontraron esos elementos del inventario"
                    ];
                }

                Inventario::whereIn('id', $inventario->pluck('id'))
                    ->update([
                        "estado" => 5,
                        "activo" => 0,
                        ]);

                $registros = [];

                foreach ($inventario as $inv) {
                    $registros[] = [
                        "id_inventario" => $inv->id,
                        "id_log" => $id_log
                    ];
                }

                InventarioDescontinuado::insert($registros);

                return [
                    "error" => false,
                    "message" => "Inventario descontinuado correctamente",
                    "data" => $inventario
                ];
            });

            if (!$result['error']) {

                $titulo = "Notificación | Inventario Descontinuado";
                $contenido = "Se han descontinuado los siguientes elementos:\n\n";

                foreach ($result['data'] as $inv) {
                    $contenido .= "- {$inv->descripcion} (Código: {$inv->codigo})\n";
                }

                Mail::to("hernando.ramirez@royalschool.edu.co")
                    ->send(new GenericMail($titulo, $contenido));
            }

            return $result;
        } catch (\Exception $e) {
            return [
                "error" => true,
                "message" => $e->getMessage(),
                "data" => null,
            ];
        }
    }

    public function liberarInventario(array $ids, ?int $id_log = null){
        try {
            $result = DB::transaction(function () use ($ids, $id_log) {
                $inventario = Inventario::whereIn('id', $ids)
                            ->whereNotIn('estado', [4, 5])    
                            ->get();

                if($inventario->isEmpty()){
                    return [
                        'error' => true,
                        'message' => 'No se encontraron esos elementos en el inventario',
                        'data' => null,
                    ];
                }

                Inventario::whereIn('id', $inventario->pluck('id'))
                    ->update(["estado" => 4,
                                "id_user" => null,
                                "id_area" => null,
                            ]);

                $registros = [];

                foreach($inventario as $i){
                    $registros[] = [
                        'id_inventario' => $i->id,
                        'id_log' => $id_log,
                    ];
                }

                InventarioLiberado::insert($registros);

                return [
                    "error" => false,
                    "message" => "Inventario Liberado correctamente",
                    "data" => $inventario
                ];
            });

            if(!$result['error']){
                $titulo = "Notificación | Inventario Liberado";
                $contenido = "Se han Liberado los siguientes elementos:\n\n";

                foreach ($result['data'] as $inv) {
                    $contenido .= "- {$inv->descripcion} (Código: {$inv->codigo})\n";
                }

                Mail::to("hernando.ramirez@royalschool.edu.co")
                    ->send(new GenericMail($titulo, $contenido));
            }

            return $result;
        }catch(\Exception $e){
            Log::error('No se liberaron los elementos: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Error liberando esos elementos: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function asignarInventario(array $ids, int $id_area, int $id_usuario){
        try{
            $inventario_liberado = Inventario::whereIn('id', $ids)
                                                ->where('estado', 4)
                                                ->get();
    
            if($inventario_liberado->isEmpty()){
                return [
                    'message' => "Ese inventario no está liberado.",
                    'data' => null,
                    'error' => true,
                ];
            }
    
            Inventario::whereIn('id', $inventario_liberado->pluck('id'))
                ->where('estado', 4)
                ->update([
                    'estado' => 1,
                    'id_area' => $id_area,
                    'id_user' => $id_usuario,
                ]);
    
            return [
                'data' => $inventario_liberado,
                'message' => "Inventario asignado",
                'error' => false,
            ];

        }catch(\Exception $e){
            Log::error("No se asigno el inventario: " . $e->getMessage());

            return [
                'data' => null,
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
}
