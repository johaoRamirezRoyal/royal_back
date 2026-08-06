<?php

namespace App\Http\Controllers\Modulos;

use App\Http\Controllers\Controller;
use App\Services\Modulos\ModulosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ModulosController extends Controller
{
    protected $modulos_services;

    public function __construct(ModulosServices $modulosServices)
    {
        $this->modulos_services = $modulosServices;
    }

    public function registrarVisita(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'modulo' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $response = $this->modulos_services->registrarVisita(Auth::id(), $request->input('modulo'));

        return $this->apiResponse($response);
    }

    public function masVisitados(Request $request)
    {
        $response = $this->modulos_services->modulosMasVisitados(Auth::id(), (int) $request->input('limite', 5));

        return $this->apiResponse($response);
    }
}
