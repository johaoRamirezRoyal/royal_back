<?php

namespace App\Http\Controllers\Documentos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    /**
     * Descarga o visualiza un documento del storage público.
     * Query params: directorio, nombre_documento, download (opcional).
     */
    public function descargar(Request $request)
    {
        $request->validate([
            'directorio' => 'required|string|max:500',
            'nombre_documento' => 'required|string|max:500',
            'download' => 'nullable|boolean',
        ]);

        $directorio = trim($request->input('directorio'), '/');
        $nombreDocumento = $request->input('nombre_documento');

        if (str_contains($directorio, '..') || str_contains($nombreDocumento, '..')) {
            return $this->error('Ruta no válida.', 400);
        }

        $ruta = $directorio . '/' . $nombreDocumento;

        if (!Storage::disk('public')->exists($ruta)) {
            return $this->error('Documento no encontrado.', 404);
        }

        if ($request->boolean('download')) {
            return Storage::disk('public')->download($ruta);
        }

        return Storage::disk('public')->response($ruta);
    }
}
