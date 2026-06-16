<?php

namespace App\Services\inventario;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioDescontinuado;
use App\Models\Inventario\InventarioLiberado;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventarioServices
{
    public function __construct(
        private MailService $mailService
    ) {}

    /**
     * Summary of mailTo
     * @var array
     */
    private array $mailTo = [
        'hernando.ramirez@royalschool.edu.co'
    ];

    /**
     * Summary of obtenerListadoInventario
     * @param mixed $perPage
     * @param mixed $search
     * @param mixed $datos
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
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
                'data' => $listado->toArray(),
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

    /**
     * Summary of agregarInventario
     * @param mixed $inventario
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function agregarInventario($inventario)
    {
        try {
            $inventario = Inventario::create($inventario);

            return [
                'error' => false,
                'data' => $inventario->toArray(),
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

    /**
     * Summary of descontinuarInventario
     * @param array $ids
     * @param mixed $id_log
     */
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

                $this->mailService->sendGeneric($this->mailTo, $titulo, $contenido);
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

    /**
     * Summary of liberarInventario
     * @param array $ids
     * @param mixed $id_log
     */
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

                $this->mailService->sendGeneric($this->mailTo, $titulo, $contenido);
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

    /**
     * Summary of asignarInventario
     * @param array $ids
     * @param int $id_area
     * @param int $id_usuario
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
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

            $titulo = "Notificación | Inventario Asignado";
            $contenido = "Se han Asignado los siguientes elementos:\n\n";


            foreach($inventario_liberado as $inv){
                $contenido .= "{$inv->descripcion} (Codigo: {$inv->id})\n";
            }

            $this->mailService->sendGeneric($this->mailTo, $titulo, $contenido);

            return [
                'data' => $inventario_liberado->toArray(),
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

    //REPORTAR INVENTARIO
    public function reportarInventario(
        array $ids, 
        int $id_log, 
        int $id_responsable, 
        string $descripcion,
        string $estado,
        int $id_anio,
        int $id_periodo
        ): array{
        return [];
    }
}
